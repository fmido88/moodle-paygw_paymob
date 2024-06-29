<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     paygw_paymob
 * @category    admin
 * @copyright   2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use paygw_paymob\legacy_requester;

require_once(__DIR__ . '/../../../config.php');
require_login();
global $DB, $USER;

$delete      = optional_param('delete', '', PARAM_INT);
$description = optional_param('description', '', PARAM_TEXT);
$orderid     = required_param('orderid', PARAM_INT);
$sesskey     = required_param('sesskey', PARAM_ALPHANUM);

// Get the rest of params in case of payment process.
$method         = optional_param('method', '', PARAM_TEXT);
$itemname       = $description;
$walletnumber   = optional_param('phone-number', 0, PARAM_INT);
$savedcardtoken = optional_param('card_token', false, PARAM_RAW);

$order = new paygw_paymob\order($orderid);
$returnbackurl = new moodle_url('/payment/gateway/paymob/method.php',
                                        [
                                            'component'   => $order->get_component(),
                                            'paymentarea' => $order->get_paymentarea(),
                                            'itemid'      => $order->get_itemid(),
                                            'description' => $description,
                                        ]);

// Check first if the user try to delete old card, and send back the required params.
if (!empty($delete) && confirm_sesskey()) {
    $DB->delete_records('paygw_paymob_cards_token', ['id' => $delete]);

    $msg = get_string('card_deleted', 'paygw_paymob');
    redirect($returnbackurl, $msg);
}
$params = [
    'orderid'      => $orderid,
    'description'  => $description,
    'method'       => $method,
    'phone-number' => $walletnumber,
    'card-token'   => $savedcardtoken,
    'sesskey'      => $sesskey,
];
// Get all configuration preference.
$config = $order->get_gateway_config();
$successurl = $order->get_redirect_url();
$cost = $order->get_cost();

// Because the amount sent to paymob is in censts.
$fee = $order->get_amount_cents();
$currency = $order->get_currency();

$apikey = $config->apikey;

$helper = new legacy_requester($apikey);

$error = get_string('somethingwrong', 'paygw_paymob');

$methods = paygw_paymob\utils::get_payment_methods($config, $currency);
if ($method == 'wallet' && array_key_exists('wallet', $methods)) {
    // Get the integration id of this payment method.
    $intid = $methods['wallet'];

    // Requesting all data need to complete the payment using this method.
    $wallet = $helper->request_wallet_url($walletnumber, $description, $fee, $currency, $intid);

    if (empty($wallet)) {
        redirect($returnbackurl, $error, null, 'error');
    }

    $orderid   = $wallet->orderid;
    $walleturl = $wallet->redirecturl;
    $iframeurl = $wallet->iframeurl;
    $method    = $wallet->method;

    // Updating the record so we can reuse it in the callback process.
    $order->set_pm_orderid($wallet->orderid, false);
    $order->update_status('pending');
    // Redirect the user to the payment page.
    if (!empty($walleturl)) {
        redirect($walleturl);

    } else if (!empty($iframeurl)) {
        redirect($iframeurl);
    } else {
        redirect($returnbackurl, $error, null, 'error');;
    }

} else if ($method == 'aman' && isset($methods['aman'])) {
    // Get the integration id of this payment method.
    $intid       = $methods['aman'];

    // Requesting all data need to complete the payment using this method.
    $kiosk = $helper->request_kiosk_id($itemname, $fee, $currency, $intid);
    if (empty($kiosk)) {
        redirect($returnbackurl, $error, null, 'error');
    }

    $orderid   = $kiosk->orderid;
    $reference = $kiosk->reference;
    $method    = $kiosk->method;

    // Updating the record so we can reuse it in the callback process.
    $order->set_pm_orderid($kiosk->orderid, false);
    $order->update_status('pending');
    // Set the context of the page.
    $PAGE->set_context(context_system::instance());

    $PAGE->set_url('/payment/gateway/paymob/process.php', $params);
    $title = get_string('aman_key', 'paygw_paymob');
    $PAGE->set_title($title);
    $PAGE->set_heading($title);

    $PAGE->set_cacheable(false);
    $PAGE->set_pagelayout('frontpage');
    echo $OUTPUT->header();

    $templatedata = new stdClass;
    $templatedata->ref = $reference;
    $templatedata->show_accept_iframe = false;

    $continuelabel = get_string('success_continue', 'paygw_paymob');

    $templatedata->continue = $OUTPUT->single_button($successurl, $continuelabel);

    echo $OUTPUT->render_from_template('paygw_paymob/process', $templatedata);

    echo $OUTPUT->footer();
    exit;

} else if ($method == 'card' && isset($methods['card']) && !empty($config->iframe_id)) {
    // Get the integration id of this payment method.
    $intid = $methods['card'];
    // Get the iframe id for card payments.
    $iframeid = $config->iframe_id;

    // Requesting all data need to complete the payment using this method.
    $request = $helper->request_payment_key($itemname, $fee, $currency, $intid, $savedcardtoken);
    if (empty($request)) {
        redirect($returnbackurl, $error, null, 'error');
    }

    $token   = $request->paytoken;
    $orderid = $request->orderid;

    // Updating the record so we can reuse it in the callback process.
    $order->set_pm_orderid($request->orderid, false);
    $order->update_status('pending');
    // Set the context of the page.
    $PAGE->set_context(context_system::instance());

    $PAGE->set_url('/payment/gateway/paymob/process.php', $params);
    $PAGE->set_title(format_string('Payment with bank card'));
    $PAGE->set_heading(format_string('Payment with bank card'));

    $PAGE->set_cacheable(false);
    $PAGE->set_pagelayout('popup');

    echo $OUTPUT->header();

    // Set the final iframe url.
    $iframe = 'https://accept.paymobsolutions.com/api/acceptance/iframes/'.$iframeid.'?payment_token='.$token;

    $templatedata = new stdClass;
    $templatedata->iframeurl          = $iframe;
    $templatedata->show_accept_iframe = true;

    echo $OUTPUT->render_from_template('paygw_paymob/process', $templatedata);

    echo $OUTPUT->footer();
    exit;

} else {
    redirect($returnbackurl, get_string('invalidmethod', 'paygw_paymob'));
}
