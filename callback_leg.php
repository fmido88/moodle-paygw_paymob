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
 * The callback process is ocurred at this page.
 *
 * This page now  used  for legacy api.
 *
 * @package     paygw_paymob
 * @copyright   2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use paygw_paymob\notifications;
use paygw_paymob\security;
use paygw_paymob\order;

// This file is just a reference for old callback process
// Adding moodle internal check will prevent any access for the file.
defined('MOODLE_INTERNAL') || die();
require_admin();
// Does not require login in server side transaction process callback.
// But it is required in client side transaction response callback.
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
global $DB;
static $process;

$requestmethod = $_SERVER['REQUEST_METHOD'];
// Paymob do two kinds of callbacks.
// First one transaction processed callback
// is in request method POST and it is in json format.
// This kind is transaction response callback.
// Second one is in request method GET.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postdata = file_get_contents('php://input');
    $jsondata = json_decode($postdata, true);
    // The object data we need.
    $obj = $jsondata['obj'];
    // Prepare the string for hmac hash calculation.
    $type = $jsondata['type'];
    if ($type === 'TRANSACTION') {
        $orderid = $obj['order']['id'];

    } else if ($type === 'DELIVERY_STATUS') {
        $orderid = $obj['order']['id'];

    } else if ($type === 'TOKEN') {
        $orderid = $obj['order_id'];
    }

} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // This is a client side not server side, so let's make sure that the user is logged in.
    require_login(null, false);
    // Get all the data we need from the request.
    $obj = [
        'success'       => optional_param('success', '', PARAM_TEXT),
        'is_voided'     => optional_param('is_voided', '', PARAM_TEXT),
        'is_refunded'   => optional_param('is_refunded', '', PARAM_TEXT),
        'pending'       => optional_param('pending', '', PARAM_TEXT),
        'is_void'       => optional_param('is_void', '', PARAM_TEXT),
        'is_refund'     => optional_param('is_refund', '', PARAM_TEXT),
        'error_occured' => optional_param('error_occured', '', PARAM_TEXT),
        'data_message'  => optional_param('data_message', '', PARAM_TEXT),
    ];
    $jsondata = [];
    $type = 'TRANSACTION';
    $orderid = required_param('order', PARAM_INT);
} else {
    die('METHOD "' . $_SERVER['REQUEST_METHOD'] . '" NOT ALLOWED');
}

// Check the hmac code from request.
$hmac = optional_param('hmac', '', PARAM_TEXT);
$order = order::instance_form_pm_orderid($orderid);

// Use the api key and the hmac-secret from the configuration.
$config = $order->get_gateway_config();
$hmacsecret = $config->hmac;

$cost = $order->get_raw_cost();
$url = $order->get_redirect_url();

// Initialize the data to be updated in the database.

// Secure connection? Verify that the hmac sent is exactly the same as that we calculated using hmac-secret.
if (security::verify_legacy_hmac($hmacsecret, $jsondata, $type)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($type == 'TRANSACTION') {
            $status = paygw_paymob\utils::get_order_status($obj);
            // Successful payment.
            if ($status === 'success') {


                // Check if there is a receipt from paymob.
                if (isset($obj['data']['receipt_url'])) {
                    // Send the user the receipt url.
                    notifications::send_receipt_url($userid, $cost, $orderid, $obj['data']['receipt_url']);
                }

                // Check if this payment is a down payment.
                if (isset($obj['data']['down_payment']) && isset($obj['data']['currency'])) {
                    // Not likely that this will occur in moodle payment but it is an option in paymob.
                    // So this notification will be helpful if the state from paymob is down payment and
                    // we don't accept down payments here to track what is gone wrong.
                    // Also, it may be helpful in the future if we add an option of down-payment for a course
                    // for example and the rest after some time. For now just notification will be good.
                    // Notify user that this payment is a down payment.
                    notifications::notify($order, 'downpayment');
                }

                $order->verify_order_changeable(true, $status);
                $order->payment_complete();

            } else if ($status === 'refunded') {

                $order->update_status('refunded');
                notifications::notify($order, 'refunded');

            } else if ($status === 'voided') {

                $order->update_status('voided');
                notifications::notify($order, 'voided');

            } else if ( // Pending payments, This means that the user didn't complete it yet.
                $obj['success'] === false &&
                $obj['is_voided'] === false &&
                $obj['is_refunded'] === false &&
                $obj['is_void'] === false &&
                $obj['is_refund'] === false
            ) {

                $order->update_status('pending');
                notifications::notify($order, 'pending');

            }

            $order->add_note_from_transaction($obj);

            die("Order updated: $orderid");
        } else if ($type == 'TOKEN') {
            // This mean that the user choose to save card during payment.
            $tablename = 'paygw_paymob_cards_token';

            $user = core_user::get_user_by_email($obj['email']);

            // Check if this user exists.
            // Also for security that is the same user who done the order.
            if ($user && $userid == $user->id) {
                // Check if this card already exists.
                $tokendata = [
                    'user_id'      => $userid,
                    'card_subtype' => $obj['card_subtype'],
                    'masked_pan'   => $obj['masked_pan'],
                ];
                $token = $DB->get_record($tablename, $tokendata);

                // Not exist? insert new record.
                if (empty($token)) {
                    $tokendata['token'] = $obj['token'];
                    $DB->insert_record($tablename, $tokendata);

                } else { // Exists? update the data.
                    $tokenid = $DB->get_field($tablename, 'id', $tokendata);
                    $tokendata['token'] = $obj['token'];
                    $tokendata['id']    = $tokenid;
                    $DB->update_record($tablename, (object)$tokendata);
                }
                // Todo notify the user that the card has been saved or updated.
                die("Token Saved: user id: $userid, user email: " . $obj['email']);
            }
        }
        // If type is DELIVERY_STATUS this should notify the user about the delivery.
        // In moodle, users pays for courses, but may be we can use it for selling books or somthing like that.
        // Todo notify the users about deliver status just in case.
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // This is the second callback from paymob, this is transaction response callback.
        // When the transaction is success, there is two ways that paymob send the response.
        // First that success is true.
        if (
            (
            $obj['success'] === "true" &&
            $obj['is_voided'] === "false" &&
            $obj['is_refunded'] === "false" &&
            $obj['pending'] === "false" &&
            $obj['is_void'] === "false" &&
            $obj['is_refund'] === "false" &&
            $obj['error_occured'] === "false"
            ) || // Second case Approved in data message.
            (!empty($obj['data_message']) &&
            $obj['data_message'] === "Approved" )) {

            // Check the actual status from the transaction process callback.
            $status = $order->get_status();
            redirect($url, get_string('paymentresponse', 'paygw_paymob', $status), null, 'success');
        } else { // Otherwise the transaction is declined.
            $order->update_status('declined');

            if (empty($obj['data_message'])) {
                $a = 'UNKNOWN';
            } else {
                $a = $obj['data_message'];
            }
            $params = [
                'component'   => $order->get_component(),
                'itemid'      => $order->get_itemid(),
                'paymentarea' => $order->get_paymentarea(),
                'description' => '',
            ];
            // Notify user with the reason of declination if it is set.
            notifications::notify($order, 'declined', $a);
            redirect(new moodle_url('/payment/gateway/paymob/method.php', $params),
                        get_string('paymentcancelled', 'paygw_paymob', $a), null, 'error');
        }
    }
} else {
    die("This Server is busy try again later!");
}
