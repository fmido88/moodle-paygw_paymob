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
use core_payment\helper;
use paygw_paymob\paymob_helper;

require_once(__DIR__ . '/../../../config.php');
require_login();
global $DB, $USER;

$delete      = optional_param('delete', '', PARAM_INT);
$component   = required_param('component', PARAM_TEXT);
$paymentarea = required_param('paymentarea', PARAM_TEXT);
$description = optional_param('description', '', PARAM_TEXT);
$itemid      = required_param('itemid', PARAM_INT);

$returnbackurl = new moodle_url('/payment/gateway/paymob/method.php',
                                        [
                                            'component'   => $component,
                                            'paymentarea' => $paymentarea,
                                            'description' => $description,
                                            'itemid'      => $itemid,
                                        ]);

// Check first if the user try to delete old card, and send back the required params.
if (!empty($delete)) {
    $DB->delete_records('paygw_paymob_cards_token', ['id' => $delete]);

    $msg = get_string('card_deleted', 'paygw_paymob');
    redirect($returnbackurl, $msg);
}

// Get the rest of params in case of payment process.
$method         = optional_param('method', '', PARAM_TEXT);
$itemname       = optional_param('itemname', '', PARAM_TEXT);
$walletnumber   = optional_param('phone-number', 0, PARAM_INT);
$savedcardtoken = optional_param('card_token', false, PARAM_RAW);

$params = [
    'component'    => $component,
    'paymentarea'  => $paymentarea,
    'description'  => $description,
    'itemid'       => $itemid,
    'method'       => $method,
    'itemname'     => $itemname,
    'phone-number' => $walletnumber,
    'card-token'   => $savedcardtoken,
];
// Get all configuration perefernce.
$config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'paymob');

$payable = helper::get_payable($component, $paymentarea, $itemid);// Get currency and payment amount.
$surcharge = helper::get_gateway_surcharge('paymob');// In case user uses surcharge.
$successurl = helper::get_success_url($component, $paymentarea, $itemid);
$cost = helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge);

// Check if there is enabled discounts and this payment within the conditions.
if (
    isset($config->discount)
    && $config->discount > 0
    && isset($config->discountcondition)
    && $cost >= $config->discountcondition
    ) {
    // Apply the discount.
    $cost = $cost * (100 - $config->discount) / 100;
}
$data = new \stdClass;
$data->component   = $component;
$data->paymentarea = $paymentarea;
$data->itemid      = $itemid;
$data->method      = $method;
$data->status      = 'requested';

// Because the amount sent to paymob is in censts.
$fee = $cost * 100;
$currency = $payable->get_currency();

$apikey = $config->apikey;

$helper = new paymob_helper($apikey);

$error = get_string('somethingwrong', 'paygw_paymob');

if ($method == 'wallet') {
    // Get the integration id of this payment method.
    $intid = $config->IntegrationIDwallet;
    $data->intid       = $intid;
    // Requesting all data need to complete the payment using this method.
    $wallet = $helper->request_wallet_url($walletnumber, $description, $fee, $currency, $intid);

    if (empty($wallet)) {
        redirect($returnbackurl, $error, null, 'error');
    }

    $orderid   = $wallet->orderid;
    $walleturl = $wallet->redirecturl;
    $iframeurl = $wallet->iframeurl;
    $method    = $wallet->method;

    $id = $DB->get_field('paygw_paymob', 'id', ['pm_orderid' => $orderid]);
    $data->id     = $id;
    $data->pm_orderid  = $orderid;
    $data->method = $method;

    // Updating the record so we can reuse it in the callback process.
    $DB->update_record('paygw_paymob', $data);
    // Redirect the user to the payment page.
    if (!empty($walleturl)) {
        redirect($walleturl);

    } else if (!empty($iframeurl)) {
        redirect($iframeurl);
    } else {
        redirect($returnbackurl, $error, null, 'error');;
    }

} else if ($method == 'kiosk') {
    // Get the integration id of this payment method.
    $intid = $config->IntegrationIDkiosk;
    $data->intid       = $intid;
    // Requesting all data need to complete the payment using this method.
    $kiosk = $helper->request_kiosk_id($itemname, $fee, $currency, $intid);
    if (empty($kiosk)) {
        redirect($returnbackurl, $error, null, 'error');
    }
    $orderid   = $kiosk->orderid;
    $reference = $kiosk->reference;
    $method    = $kiosk->method;
    $data->pm_orderid  = $orderid;

    $id = $DB->get_field('paygw_paymob', 'id', ['pm_orderid' => $orderid]);
    $data->id     = $id;
    $data->method = $method;

    // Updating the record so we can reuse it in the callback process.
    $DB->update_record('paygw_paymob', $data);
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

} else if ($method == 'card') {
    // Get the integration id of this payment method.
    $intid = $config->IntegrationIDcard;
    $data->intid       = $intid;
    // Get the iframe id for card payments.
    $iframeid = $config->iframe_id;

    // Requesting all data need to complete the payment using this method.
    $request = $helper->request_payment_key($itemname, $fee, $currency, $intid, $savedcardtoken);
    if (empty($request)) {
        redirect($returnbackurl, $error, null, 'error');
    }
    $token   = $request->paytoken;
    $orderid = $request->orderid;
    $data->pm_orderid  = $orderid;

    $id = $DB->get_field('paygw_paymob', 'id', ['pm_orderid' => $orderid]);
    $data->id = $id;

    // Updating the record so we can reuse it in the callback process.
    $DB->update_record('paygw_paymob', $data);
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
