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

global $CFG, $USER, $DB;

$component   = required_param('component', PARAM_ALPHANUMEXT);
$paymentarea = required_param('paymentarea', PARAM_ALPHANUMEXT);
$itemid      = required_param('itemid', PARAM_INT);
$description = required_param('description', PARAM_TEXT);

$params = [
    'component'   => $component,
    'paymentarea' => $paymentarea,
    'itemid'      => $itemid,
    'description' => $description,
];

$config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'paymob');

$payable = helper::get_payable($component, $paymentarea, $itemid);// Get currency and payment amount.
$surcharge = helper::get_gateway_surcharge('paymob');// In case user uses surcharge.
// Adding discount condition.
$cost = helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge);
if (isset($config->discount)
    && $config->discount > 0 &&
    isset($config->discountcondition) &&
    $cost >= $config->discountcondition) {

    $cost = $cost * (100 - $config->discount) / 100;
}

$fee = $cost * 100; // Because paymob get the cost in cents.
$currency = $payable->get_currency();

$apikey = $config->apikey;
$helper = new paymob_helper($apikey);

// Set the context of the page.
$PAGE->set_context(context_system::instance());

$PAGE->set_url('/payment/gateway/paymob/method.php', $params);
$PAGE->set_title(format_string('Paying for '.$description));
$PAGE->set_heading(format_string('Paying for '.$description));

// Set the appropriate headers for the page.
$PAGE->set_cacheable(false);
$PAGE->set_pagelayout('frontpage');

echo $OUTPUT->header();

$templatedata = new stdClass;
$templatedata->component   = $component;
$templatedata->paymentarea = $paymentarea;
$templatedata->itemid      = $itemid;
$templatedata->description = $description;
$templatedata->fee         = $fee / 100;
$templatedata->currency    = $currency;
$templatedata->itemname    = $description;
$templatedata->url         = $CFG->wwwroot;
$templatedata->saved       = false;
$templatedata->hascard     = (!empty($config->IntegrationIDcard) && !empty($config->iframe_id));
$templatedata->haswallet   = !empty($config->IntegrationIDwallet);
$templatedata->haskiosk    = !empty($config->IntegrationIDkiosk);

$cards = $DB->get_records('paygw_paymob_cards_token', ['userid' => $USER->id]);
$templatedata->validcurrency = ($currency == 'EGP') ? true : false;

if (!empty($cards)) {
    $templatedata->saved = true;
    $templatedata->savedcardsnotify = get_string('savedcardsnotify', 'paygw_paymob', fullname($USER));
    $savedcards = [];
    foreach ($cards as $key => $card) {
        $savedcards['card_'.$key] = new stdClass;
        foreach ($card as $c => $i) {
            $savedcards['card_'.$key]->$c = $i;
        }
    }
    $templatedata->savedcards = array_values($savedcards);
}

echo $OUTPUT->render_from_template('paygw_paymob/method', $templatedata);

echo $OUTPUT->footer();
