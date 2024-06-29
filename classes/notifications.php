<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/** Notifications for paygw_paymob.
 *
 *
 * @package    paygw_paymob
 * @copyright  2023 Mo Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace paygw_paymob;

/** Notifications class.
 *
 * Handle notifications for users about their transactions.
 *
 * @package    paygw_paymob
 * @copyright  2023 Mo Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifications {

    /**
     * The order is processing or need action.
     */
    public const PROCESSING = 'processing';
    /**
     * The order completed
     */
    public const COMPLETED = 'completed';
    /**
     * Pending
     */
    public const PENDING = 'pending';
    /**
     * The transaction voided
     */
    public const VOIDED = 'voided';
    /**
     * The transaction refunded
     */
    public const REFUNDED = 'refunded';
    /**
     * Declined transactions
     */
    public const DECLINED = 'declined';
    /**
     * Down-payment
     */
    public const DOWN_PAYMENT = 'downpayment';
    /**
     * Function that handle the notifications about transactions using Paymob payment gateway
     * and all kinds of responses.
     *
     * this function sending the message to the user and return the id of the message if needed
     * or false in case of error.
     *
     * @param int $userid
     * @param float $fee
     * @param int $orderid
     * @param string $type
     * @param string $reason
     * @return int|false
     */
    public static function notify($userid, $fee, $orderid, $type, $reason = '') {
        global $DB;

        // Get the item from the database, and payable to get the currency.
        $item = $DB->get_record('paygw_paymob', ['pm_orderid' => $orderid]);
        $payable = \core_payment\helper::get_payable($item->component, $item->paymentarea, $item->itemid);

        // Get the user object for messaging and fullname.
        $user = \core_user::get_user($userid);
        if (empty($user) || isguestuser($user) || !empty($user->deleted)) {
            return false;
        }

        $userfullanme = fullname($user);

        // Set the object wiht all informations to notify the user.
        $a = (object)[
            'fee'      => $fee, // The original cost.
            'cost'     => $item->cost, // The cost after discounts.
            'currency' => $payable->get_currency(),
            'status'   => $type,
            'reason'   => $reason, // The reason in case of declination.
            'orderid'  => $orderid,
            'fullname' => $userfullanme,
        ];

        switch ($item->method) {
            case('card'):
                $a->method = get_string('method_card', 'paygw_paymob');
                break;
            case('wallet'):
                $a->method = get_string('method_wallet', 'paygw_paymob');
                break;
            case('kiosk'):
                $a->method = get_string('method_kiosk', 'paygw_paymob');
                break;
            default:
                $a->method = 'not defined';
        }

        $message = new \core\message\message();
        $message->component = 'paygw_paymob';
        $message->name      = 'payment_transaction'; // The notification name from message.php.
        $message->userfrom  = \core_user::get_noreply_user(); // If the message is 'from' a specific user you can set them here.
        $message->userto    = $user;
        $message->subject   = get_string('messagesubject', 'paygw_paymob', $type);
        switch ($type) {
            case 'success_processing':
                $messagebody = get_string('message_success_processing', 'paygw_paymob', $a);
                break;
            case 'success_completed':
                $messagebody = get_string('message_success_completed', 'paygw_paymob', $a);
                break;
            case 'pending':
                $messagebody = get_string('message_pending', 'paygw_paymob', $a);
                break;
            case 'voided':
                $messagebody = get_string('message_voided', 'paygw_paymob', $a);
                break;
            case 'refunded':
                $messagebody = get_string('message_refunded', 'paygw_paymob', $a);
                break;
            case 'downpayment':
                $messagebody = get_string('message_downpayment', 'paygw_paymob', $a);
                break;
            case 'declined':
                $messagebody = get_string('message_declined', 'paygw_paymob', $a);
                break;
        }

        $header = get_string('payment_notification', 'paygw_paymob');

        $message->fullmessage       = $messagebody;
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml   = "<p>$messagebody</p>";
        $message->smallmessage      = get_string('payment_attention', 'paygw_paymob', $type);
        $message->notification      = 1; // Because this is a notification generated from Moodle, not a user-to-user message.
        $message->contexturl        = ''; // A relevant URL for the notification.
        $message->contexturlname    = ''; // Link title explaining where users get to for the contexturl.
        $content = ['*' => ['header' => $header, 'footer' => '']]; // Extra content for specific processor.
        $message->set_additional_content('email', $content);

        // Actually send the message.
        $messageid = message_send($message);

        return $messageid;
    }

    /**
     * Sending the reciept url for user if it is sent from Paymob.
     *
     * this function sending the message to the user and return the id of the message if needed
     * or false in case of error.
     * @param int $userid
     * @param float $fee
     * @param int $orderid
     * @param string $url
     * @return int|false
     */
    public static function send_receipt_url($userid, $fee, $orderid, $url) {
        global $DB;

        $item = $DB->get_record('paygw_paymob', ['pm_orderid' => $orderid]);

        $user = \core_user::get_user($userid);

        if (empty($user) || isguestuser($user)) {
            return false;
        }

        $a = (object)[
            'fee'      => $fee, // Cost before discount.
            'cost'     => $item->cost, // Cost after discount.
            'method'   => $item->method,
            'url'      => $url,
            'fullname' => fullname($user),
        ];

        $message = new \core\message\message();
        $message->component = 'paygw_paymob';
        $message->name      = 'payment_receipt'; // The notification name from message.php.
        $message->userfrom  = \core_user::get_noreply_user(); // If the message is 'from' a specific user you can set them here.
        $message->userto    = $user;
        $message->subject   = get_string('messagesubject_receipt', 'paygw_paymob');

        $messagebody = get_string('message_payment_receipt', 'paygw_paymob', $a);

        $header = get_string('payment_notification', 'paygw_paymob');

        $message->fullmessage       = $messagebody;
        $message->fullmessageformat = FORMAT_MARKDOWN;
        $message->fullmessagehtml   = "<p>$messagebody</p>";
        $message->smallmessage      = get_string('payment_attention_receipt', 'paygw_paymob');
        $message->notification      = 1; // Because this is a notification generated from Moodle, not a user-to-user message.
        $message->contexturl        = $url; // A relevant URL for the notification.
        $message->contexturlname    = get_string('payment_receipt_url', 'paygw_paymob');
        $content = ['*' => ['header' => $header, 'footer' => '']]; // Extra content for specific processor.
        $message->set_additional_content('email', $content);

        // Actually send the message.
        $messageid = message_send($message);

        return $messageid;
    }
}

