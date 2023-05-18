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

$delete = optional_param('delete', '', PARAM_INT);
$component = required_param('component', PARAM_TEXT);
$paymentarea = required_param('paymentarea', PARAM_TEXT);
$description = optional_param('description', '', PARAM_TEXT);
$itemid = required_param('itemid', PARAM_INT);

$returnbackurl = new moodle_url('/payment/gateway/paymob/method.php',
                                        [
                                            'component' => $component,
                                            'paymentarea' => $paymentarea,
                                            'description' => $description,
                                            'itemid' => $itemid,
                                        ]);

// Check first if the user try to delete old card, and send back the required params.
if ($delete) {
    $DB->delete_records('paygw_paymob_accept_cards_token', ['id' => $delete]);

    $msg = get_string('card_deleted', 'paygw_paymob');
    redirect($returnbackurl, $msg);
    exit;
}

// Get the rest of params in case of payment process.
$method = optional_param('method', '', PARAM_TEXT);
$itemname = optional_param('itemname', '', PARAM_TEXT);
$walletnumber = optional_param('phone-number', 0, PARAM_INT);
$savedcardtoken = optional_param('card_token', false, PARAM_RAW);

// Get all configuration perefernce.
$config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'paymob');

$payable = helper::get_payable($component, $paymentarea, $itemid);// Get currency and payment amount.
$surcharge = helper::get_gateway_surcharge('paymob');// In case user uses surcharge.

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

// Because the amount sent to paymob is in censts.
$fee = $cost * 100;
$currency = $payable->get_currency();

$apikey = $config->apikey;

$helper = new paygw_paymob\paymob_helper($apikey);

if ($method == 'wallet') {
    // Get the integration id of this payment method.
    $intid = $config->IntegrationIDwallet;

    // Requesting all data need to complete the payment using this method.
    $wallet = $helper->request_wallet_url($walletnumber, $description, $fee, $currency, $intid);

    $orderid = $wallet->orderid;
    $walleturl = $wallet->redirecturl;
    $iframeurl = $wallet->iframeurl;
    $method = $wallet->method;

    $id = $DB->get_field('paygw_paymob', 'id', ['pm_orderid' => $orderid]);

    $data = new \stdClass;
    $data->id = $id;
    $data->pm_orderid = $orderid;
    $data->component = $component;
    $data->paymentarea = $paymentarea;
    $data->itemid = $itemid;
    $data->intid = $intid;
    $data->method = $method;
    $data->status = 'requested';
    // Updating the record so we can reuse it in the callback proccess.
    $DB->update_record('paygw_paymob', $data);
    // Redirect the user to the payment page.
    redirect($walleturl);
    exit;
} else if ($method == 'kiosk') {
    // Get the integration id of this payment method.
    $intid = $config->IntegrationIDkiosk;

    // Requesting all data need to complete the payment using this method.
    $kiosk = $helper->request_kiosk_id($itemname, $fee, $currency, $intid);
    $orderid = $kiosk->orderid;
    $reference = $kiosk->reference;
    $method = $kiosk->method;

    $id = $DB->get_field('paygw_paymob', 'id', ['pm_orderid' => $orderid]);

    $data = new \stdClass;
    $data->id = $id;
    $data->pm_orderid = $orderid;
    $data->component = $component;
    $data->paymentarea = $paymentarea;
    $data->itemid = $itemid;
    $data->intid = $intid;
    $data->method = $method;
    $data->status = 'requested';
    // Updating the record so we can reuse it in the callback proccess.
    $DB->update_record('paygw_paymob', $data);
    // Set the context of the page.
    $PAGE->set_context(context_system::instance());

    $PAGE->set_url('/blocks/credit_display/action.php', ['id' => $USER->id]);
    $PAGE->set_title(format_string('Reference key for aman or masary'));
    $PAGE->set_heading(format_string('Reference key for aman or masary'));

    // Set the appropriate headers for the page.
    $PAGE->set_cacheable(false);
    $PAGE->set_pagetype('popup');
    echo $OUTPUT->header();

    $templatedata = new stdClass;
    $templatedata->ref = $reference;
    $templatedata->show_accept_iframe = false;

    echo $OUTPUT->render_from_template('paygw_paymob/process', $templatedata);

    echo $OUTPUT->footer();

} else if ($method == 'card') {
    // Get the integration id of this payment method.
    $intid = $config->IntegrationIDcard;
    // Get the iframe id for card payments.
    $iframeid = $config->iframe_id;

    // Requesting all data need to complete the payment using this method.
    $request = $helper->request_payment_key($itemname, $fee, $currency, $intid, $savedcardtoken);
    $token = $request->paytoken;
    $orderid = $request->orderid;

    $id = $DB->get_field('paygw_paymob', 'id', ['pm_orderid' => $orderid]);

    $data = new \stdClass;
    $data->id = $id;
    $data->pm_orderid = $orderid;
    $data->component = $component;
    $data->paymentarea = $paymentarea;
    $data->itemid = $itemid;
    $data->intid = $intid;
    $data->method = 'card';
    $data->status = 'requested';
    // Updating the record so we can reuse it in the callback proccess.
    $DB->update_record('paygw_paymob', $data);
    // Set the context of the page.
    $PAGE->set_context(context_system::instance());

    $PAGE->set_url('/blocks/credit_display/action.php', ['id' => $USER->id]);
    $PAGE->set_title(format_string('Payment with bank card'));
    $PAGE->set_heading(format_string('Payment with bank card'));

    // Set the appropriate headers for the page.
    $PAGE->set_cacheable(false);
    $PAGE->set_pagetype('popup');

    echo $OUTPUT->header();

    // Set the final iframe url.
    $iframe = 'https://accept.paymobsolutions.com/api/acceptance/iframes/'.$iframeid.'?payment_token='.$token;

    $templatedata = new stdClass;
    $templatedata->iframeurl = $iframe;
    $templatedata->show_accept_iframe = true;

    echo $OUTPUT->render_from_template('paygw_paymob/process', $templatedata);

    echo $OUTPUT->footer();

} else {
    redirect($returnbackurl, get_string('invalidmethod', 'paygw_paymob'));
}
