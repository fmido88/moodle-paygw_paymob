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
 * The callback procees is ocurred at this page.
 *
 * @package     paygw_paymob
 * @copyright   2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use core_payment\helper;
use paygw_paymob\notifications;
use paygw_paymob\paymob_helper;

// Does not require login in server side transaction process callback.
// But it is required in client side transaction response callback.
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
global $DB;

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
    $string = $jsondata['obj']; // Same as $obj in this kind of request.
    $type = $jsondata['type'];
    if ($type === 'TRANSACTION') {
        // Decoding string.
        $string['order'] = $string['order']['id'];
        $string['is_3d_secure'] = ($string['is_3d_secure'] === true) ? 'true' : 'false';
        $string['is_auth'] = ($string['is_auth'] === true) ? 'true' : 'false';
        $string['is_capture'] = ($string['is_capture'] === true) ? 'true' : 'false';
        $string['is_refunded'] = ($string['is_refunded'] === true) ? 'true' : 'false';
        $string['is_standalone_payment'] = ($string['is_standalone_payment'] === true) ? 'true' : 'false';
        $string['is_voided'] = ($string['is_voided'] === true) ? 'true' : 'false';
        $string['success'] = ($string['success'] === true) ? 'true' : 'false';
        $string['error_occured'] = ($string['error_occured'] === true) ? 'true' : 'false';
        $string['has_parent_transaction'] = ($string['has_parent_transaction'] === true) ? 'true' : 'false';
        $string['pending'] = ($string['pending'] === true) ? 'true' : 'false';
        $string['source_data_pan'] = $string['source_data']['pan'];
        $string['source_data_type'] = $string['source_data']['type'];
        $string['source_data_sub_type'] = $string['source_data']['sub_type'];
        $orderid = $string['order'];
    } else if ($type === 'DELIVERY_STATUS') {
        $string['order'] = $string['order']['id'];
        $orderid = $string['order'];
    } else if ($type === 'TOKEN') {
        $orderid = $obj['order_id'];
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // This is a client side not server side, so let's make sure that the user is logged in.
    require_login();
    // Get all the data we need from the request.
    $obj = [
        'success' => optional_param('success', '', PARAM_RAW),
        'is_voided' => optional_param('is_voided', '', PARAM_RAW),
        'is_refunded' => optional_param('is_refunded', '', PARAM_RAW),
        'pending' => optional_param('pending', '', PARAM_RAW),
        'is_void' => optional_param('is_void', '', PARAM_RAW),
        'is_refund' => optional_param('is_refund', '', PARAM_RAW),
        'error_occured' => optional_param('error_occured', '', PARAM_RAW),
        'data_message' => optional_param('data_message', '', PARAM_RAW),
    ];

    // We only need this here for checking the security hmac.
    // So we must use the variable $_GET as it is.
    $string = $_GET;

    $type = 'TRANSACTION';
    $orderid = required_param('order', PARAM_INT);
} else {
    die('METHOD "' . $_SERVER['REQUEST_METHOD'] . '" NOT ALLOWED');
}

// Check the hmac code from request.
$hmac = optional_param('hmac', '', PARAM_TEXT);

// Get the required data for completing the payment from the databasetable.
$item = $DB->get_record('paygw_paymob', ['pm_orderid' => $orderid]);
$component = $item->component;
$paymentarea = $item->paymentarea;
$itemid = $item->itemid;
$userid = $item->userid;
$id = $item->id;
$cost = $item->cost;

// Use the api key and the hmac-secret from the configuration.
$config = (object)core_payment\helper::get_gateway_configuration($component, $paymentarea, $itemid, 'paymob');
$hmacsecret = $config->hmac_secret;
$apikey = $config->apikey;

$payable = helper::get_payable($component, $paymentarea, $itemid);
// Reset the cost before discount.
if (isset($config->discount) && $config->discount > 0 && isset($config->discountcondition) && $cost >= $config->discountcondition) {
    $cost = $cost * 100 / (100 - $config->discount);
}

// Find redirection.
$url = new moodle_url('/');
// Method only exists in 3.11+.
if (method_exists('\core_payment\helper', 'get_success_url')) {
    $url = helper::get_success_url($component, $paymentarea, $itemid);
} else if ($component == 'enrol_fee' && $paymentarea == 'fee') {
    $courseid = $DB->get_field('enrol', 'courseid', ['enrol' => 'fee', 'id' => $itemid]);
    if (!empty($courseid)) {
        $url = course_get_url($courseid);
    }
}

// Get the helper class to call hash().
$helper = new paygw_paymob\paymob_helper($apikey);

// Calculate the security hash.
$hash = $helper->hash($hmacsecret, $string, $type);

// Inisialize the data to be updated in the database.
$update = new \stdClass;
$update->id = $id;
$update->status = 'requested';

$DB->update_record('paygw_paymob', $update);
// Secure connection? Verify that the hmax sent is exactly the same as that we calculated using hmac-secret.
if ($hash === $hmac) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($type == 'TRANSACTION') {

            // Successful payment.
            if (
                $obj['success'] === true &&
                $obj['is_voided'] === false &&
                $obj['is_refunded'] === false &&
                $obj['pending'] === false &&
                $obj['is_void'] === false &&
                $obj['is_refund'] === false &&
                $obj['error_occured'] === false
            ) {

                $paymentid = helper::save_payment($payable->get_account_id(),
                                $component,
                                $paymentarea,
                                $itemid,
                                $userid,
                                $cost,
                                $payable->get_currency(),
                                'paymob'
                            );
                $update->status = 'success';
                $update->paymentid = $paymentid;
                $DB->update_record('paygw_paymob', $update);

                helper::deliver_order($component, $paymentarea, $itemid, $paymentid, $userid);
                // Notify user.
                notifications::notify($userid, $cost, $orderid, 'success_completed', '');

                // Check if there is a receipt from paymob.
                if (isset($obj['data']['receipt_url'])) {
                    // Send the user the receipt url.
                    notifications::send_receipt_url($userid, $cost, $orderid, $obj['data']['receipt_url']);
                }

                // Check if this payment is a down payment.
                if (isset($obj['data']['down_payment']) && isset($obj['data']['currency'])) {
                    // Not likely that this will occure in moodle payment but it is an option in paymob.
                    // So this notification will be helpfull if the state from paymob is down payment and
                    // we don't accept down payments here to track what is gone wrong.
                    // Also, it may be helpful in the future if we add an option of downpayment for a course
                    // for example and the rest after some time. For now just notification will be good.
                    // Notify user that this payment is a down payment.
                    notifications::notify($userid, $cost, $orderid, 'downpayment');
                }

                // May be we don't need a redirection as this method is server side not user side.
                redirect($url, get_string('paymentsuccessful', 'paygw_paymob'), 0, 'success');

            } else if ( // Refunded payment.
                $obj['success'] === true &&
                $obj['is_refunded'] === true &&
                $obj['is_voided'] === false &&
                $obj['pending'] === false &&
                $obj['is_void'] === false &&
                $obj['is_refund'] === false
            ) {

                $update->status = 'refunded';
                $DB->update_record('paygw_paymob', $update);
                // Notify user.
                notifications::notify($userid, $cost, $orderid, 'refunded', '');
            } else if ( // Voided payment (cancelled) by vender or merchent.
                $obj['success'] === true &&
                $obj['is_voided'] === true &&
                $obj['is_refunded'] === false &&
                $obj['pending'] === false &&
                $obj['is_void'] === false &&
                $obj['is_refund'] === false
            ) {

                $update->status = 'voided';
                $DB->update_record('paygw_paymob', $update);
                // Notify user that the transaction is voided.
                notifications::notify($userid, $cost, $orderid, 'voided', '');
            } else if ( // Pending payments, This means that the user didn't complete it yet.
                $obj['success'] === false &&
                $obj['is_voided'] === false &&
                $obj['is_refunded'] === false &&
                $obj['is_void'] === false &&
                $obj['is_refund'] === false
            ) {

                $update->status = 'pending';
                $DB->update_record('paygw_paymob', $update);
                // Notify user that the transaction still pending and need his action.
                notifications::notify($userid, $cost, $orderid, 'pending', '');
            }

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
                    'user_id' => $userid,
                    'card_subtype' => $obj['card_subtype'],
                    'masked_pan' => $obj['masked_pan'],
                ];
                $token = $DB->get_record($tablename, $tokendata);

                // Not exist? insert new record.
                if (empty($token)) {
                    $tokendata['token'] = $obj['token'];
                    $DB->insert_record($tablename, $tokendata);

                } else { // Exists? update the data.
                    $tokenid = $DB->get_field($tablename, 'id', $tokendata);
                    $tokendata['token'] = $obj['token'];
                    $tokendata['id'] = $tokenid;
                    $DB->update_record($tablename, (object)$tokendata);
                }
                // TODO notify the user that the card has been saved or updated.
                die("Token Saved: user id: $userid, user email: " . $obj['email']);
            }
        }
        // If type is DELIVERY_STATUS this should notify the user about the delivery.
        // In moodle, users pays for courses, but may be we can use it for selling books or somthing like that.
        // TODO notify the users about deliver status just in case.
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

            // Check the actual status from the transaction proccess callback.
            $status = $DB->get_field('paygw_paymob', 'status', ['id' => $id], IGNORE_MISSING);
            redirect($url, get_string('paymentresponse', 'paygw_paymob', $status), 0, 'success');
            exit;
        } else { // Otherwise the transaction is declined.
            $update->status = 'Declined';
            $DB->update_record('paygw_paymob', $update);

            if (empty($obj['data_message']) || !isset($obj['data_message'])) {
                $a = 'UNKNOWN';
            } else {
                $a = $obj['data_message'];
            }
            // Notify user with the reason of declination if it is set.
            notifications::notify($userid, $cost, $orderid, 'declined', $a);
            redirect(new moodle_url('/'), get_string('paymentcancelled', 'paygw_paymob', $a));
            exit;
        }
    }
} else {
    die("This Server is busy try again later!");
}
exit;
