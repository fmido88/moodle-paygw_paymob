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
    public const COMPLETED = 'success';
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
     * @param order $order
     * @param string $type
     * @param string $reason
     * @return int|false
     */
    public static function notify($order, $type, $reason = '') {
        global $DB;

        // Get the user object for messaging and fullname.
        $user = $order->get_user();
        if (empty($user) || isguestuser($user) || !empty($user->deleted)) {
            return false;
        }

        $userfullanme = fullname($user);

        // Set the object wiht all informations to notify the user.
        $a = (object)[
            'fee'      => $order->get_raw_cost(), // The original cost.
            'cost'     => $order->get_cost(), // The cost after discounts.
            'currency' => $order->get_currency(),
            'status'   => $type,
            'reason'   => $reason, // The reason in case of declination.
            'orderid'  => $order->get_pm_orderid(),
            'fullname' => $userfullanme,
        ];

        $notes = $order->get_order_notes();
        if (!empty($notes)) {
            $note = reset($notes);
            $a->method = $note->type . ' / ' . $note->subtype;
        }

        $message = new \core\message\message();
        $message->component = 'paygw_paymob';
        $message->name      = 'payment_transaction'; // The notification name from message.php.
        $message->userfrom  = \core_user::get_noreply_user(); // If the message is 'from' a specific user you can set them here.
        $message->userto    = $user;
        $message->subject   = get_string('messagesubject', 'paygw_paymob', $type);
        $notifytype = 'error';
        switch ($type) {
            case self::PROCESSING:
                $messagebody = get_string('message_success_processing', 'paygw_paymob', $a);
                $notifyheader = get_string('notify_processing', 'paygw_paymob');
                $notifytype = 'success';
                break;
            case self::COMPLETED:
                $messagebody = get_string('message_success_completed', 'paygw_paymob', $a);
                $notifyheader = get_string('notify_success', 'paygw_paymob');
                $notifytype = 'success';
                break;
            case self::PENDING:
                $messagebody = get_string('message_pending', 'paygw_paymob', $a);
                $notifyheader = get_string('notify_pendig', 'paygw_paymob');
                $notifytype = 'info';
                break;
            case self::VOIDED:
                $messagebody = get_string('message_voided', 'paygw_paymob', $a);
                $notifyheader = get_string('notify_voided', 'paygw_paymob');
                break;
            case self::REFUNDED:
                $messagebody = get_string('message_refunded', 'paygw_paymob', $a);
                $notifyheader = get_string('notify_refunded', 'paygw_paymob');
                break;
            case self::DOWN_PAYMENT:
                $messagebody = get_string('message_downpayment', 'paygw_paymob', $a);
                $notifyheader = get_string('notify_downpayment', 'paygw_paymob');
                break;
            case 'declined':
                $messagebody = get_string('message_declined', 'paygw_paymob', $a);
                $notifyheader = get_string('notify_declined', 'paygw_paymob');
                break;
            default:
                $notifyheader = get_string('notify_error', 'paygw_paymob');
        }

        $header = get_string('payment_notification', 'paygw_paymob');

        \core\notification::add($notifyheader, $notifytype);
        if (empty($messagebody)) {
            return false;
        }

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

