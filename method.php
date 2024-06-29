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

require_once(__DIR__ . '/../../../config.php');

require_login();

global $CFG, $USER, $DB;

$component   = required_param('component', PARAM_ALPHANUMEXT);
$paymentarea = required_param('paymentarea', PARAM_ALPHANUMEXT);
$itemid      = required_param('itemid', PARAM_INT);
$description = optional_param('description', '', PARAM_TEXT);

$params = [
    'component'   => $component,
    'paymentarea' => $paymentarea,
    'itemid'      => $itemid,
    'description' => $description,
];

$order = paygw_paymob\order::created_order($component, $paymentarea, $itemid);
$config = $order->get_gateway_config();

$currency = $order->get_currency();
$cost = $order->get_cost();

$methods = paygw_paymob\utils::get_payment_methods($config, $currency);

$hascard = false;
$haswallet = false;
$hasaman = false;
$hasany = false;
foreach ($methods as $key => $id) {
    $var = 'has'.$key;
    $$var = true;
    $hasany = true;
}

if (empty($config->iframe_id)) {
    $hascard = false;
}

$min = $config->minimum_allowed ?? 0;

// Set the context of the page.
$PAGE->set_context(context_system::instance());

$PAGE->set_url('/payment/gateway/paymob/method.php', $params);
$PAGE->set_title(format_string('Paying for '.$description));
$PAGE->set_heading(format_string('Paying for '.$description));

$PAGE->set_cacheable(false);
$PAGE->set_pagelayout('frontpage');

echo $OUTPUT->header();

if ($cost >= (float)$min && $hasany) {

    $templatedata = new stdClass;
    $templatedata->description = $description;
    $templatedata->orderid     = $order->get_id();
    $templatedata->fee         = $cost;
    $templatedata->currency    = $currency;
    $templatedata->url         = $CFG->wwwroot;
    $templatedata->saved       = false;
    $templatedata->hascard     = $hascard;
    $templatedata->haswallet   = $haswallet;
    $templatedata->haskiosk    = $hasaman;
    $templatedata->sesskey     = sesskey();

    $cards = $DB->get_records('paygw_paymob_cards_token', ['userid' => $USER->id]);
    $templatedata->validcurrency = ($currency == 'EGP');

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
} else if ($hasany) {
    notice(get_string('low_payment', 'paygw_paymob', $min));
} else {
    notice(get_string('no_payment_integration', 'paygw_paymob'));
}

echo $OUTPUT->footer();
