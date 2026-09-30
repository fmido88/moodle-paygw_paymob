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

namespace paygw_paymob;

/**
 * Class actions
 *
 * @package    paygw_paymob
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class actions extends requester {
    /**
     * The order instance
     * @var order
     */
    private \paygw_paymob\order $order;

    /**
     * Ready for payment actions.
     * @param \paygw_paymob\order|int $order
     */
    public function __construct($order) {
        if (is_number($order)) {
            $this->order = new order($order);
        } else if ($order instanceof order) {
            $this->order = $order;
        } else {
            throw new \moodle_exception('Invalid order passed to constructor.');
        }

        $config = $this->order->get_gateway_config();

        parent::__construct($config->apikey, $config->public_key, $config->private_key);
    }

    /**
     * Refund transaction
     * @param int $amountcents the amount to be refunded (0 will refund the total amount)
     * @return bool
     */
    public function refund($amountcents = 0) {
        if (!$transid = $this->order->get_transaction_id(-1)) {
            self::debug('There is no transaction id for this order');
            return false;
        }

        $original = $this->order->get_amount_cents();
        if (!empty($amountcents) && $original < $amountcents) {
            self::debug('The amount to refund is more than the original amount');
            return false;
        }

        $data = [
            'transaction_id' => $transid,
            'amount_cents'   => !empty($amountcents) ? $amountcents : $original,
        ];
        $response = $this->request(self::ACTIONS['refund'], $data, 'post');
        self::log($response);
        $status = utils::get_order_status($response);
        if ($status == 'refund' || $status == 'refunded') {
            return true;
        }
        return false;
    }

    /**
     * Void a transaction.
     * @return bool
     */
    public function void() {
        if (!$transid = $this->order->get_transaction_id(-1)) {
            self::debug('There is no transaction id for this order');
            return false;
        }

        $timecreated = $this->order->timecreated;
        if ($timecreated <= time() - 23 * HOURSECS) {
            self::debug('Transaction is too old to void');
            return false;
        }

        $data = [
            'transaction_id' => $transid,
        ];
        $response = $this->request(self::ACTIONS['void'], $data, 'post');
        self::log($response);
        $status = utils::get_order_status($response);
        if ($status == 'void' || $status == 'voided') {
            return true;
        }

        return false;
    }

    /**
     * Inquiry by Transaction id
     * @param int $transid
     * @return array|bool
     */
    public function inquiry_transaction($transid = 0) {
        if (empty($transid) && !$transid = $this->order->get_transaction_id()) {
            self::debug('There is no transaction id for this order');
            return false;
        }

        $url = self::ACTIONS['inquiry_transid'];
        $url = str_replace('{transaction_id}', $transid, $url);

        $request = $this->request($url, [], 'GET');
        if (is_string($request) || empty($request->order)) {
            self::debug($request);
            return false;
        }

        return $this->parse_inquiry_data($request);
    }

    /**
     * Inquiry by order id.
     * @return array|bool
     */
    public function inquiry_order() {
        $orderid = $this->order->get_pm_orderid();
        if (empty($orderid)) {
            self::debug('There is no paymob order id for this order');
            return false;
        }
        $data = [
            'order_id'   => $orderid,
        ];
        $authheader = 'Authorization: Bearer ' . $this->get_auth_token();
        $request = $this->request(requester::ACTIONS['inquiry_orderid'], $data, 'post', $authheader);
        if (is_string($request) || empty($request->order)) {
            self::debug($request);
            return false;
        }

        return $this->parse_inquiry_data($request);
    }

    /**
     * As the requested data is to much and not all needed, so we just
     * filter it to the most required data
     * @param object $data
     * @return array
     */
    private function parse_inquiry_data($data) {
        $return = [];
        $order = $data->order;
        $card = $data->payment_source ?? $data->data->card_num;

        $return['id'] = $this->order->get_id();
        $return['pm_orderid'] = $order->id;
        $return['status'] = utils::get_order_status((array)$data);
        $return['amount_cents'] = $order->amount_cents;
        $return['paid_amount_cents'] = $order->paid_amount_cents;
        $return['refunded_amount_cents'] = $data->refunded_amount_cents ?? 0;
        $return['receipt'] = $data->data->receipt_no;
        $return['currency'] = $order->currency;
        $return['payment_method'] = "{$data->source_data->type} / {$data->source_data->sub_type} ({$card})";
        $return['message'] = $data->data->message;
        $return['timecreated'] = strtotime($order->created_at);
        $return['timeupdated'] = strtotime($data->updated_at);

        return $return;
    }

}
