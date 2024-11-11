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

use core_payment\local\entities\payable;
use core_payment\helper;

/**
 * Class order
 *
 * @package    paygw_paymob
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class order {
    /**
     * The order id
     * @var int
     */
    protected $id;
    /**
     * @var int
     */
    protected $itemid;
    /**
     * @var string
     */
    protected $component;
    /**
     * @var string
     */
    protected $paymentarea;
    /**
     * @var float
     */
    protected $rawcost;
    /**
     * @var float
     */
    protected $cost;
    /**
     * @var string
     */
    protected $currency;
    /**
     * The order status
     * @var string
     */
    protected $status;
    /**
     * @var int
     */
    public $timecreated;
    /**
     * @var int
     */
    protected $timemodified;
    /**
     * The payment id in the payments table.
     * @var int
     */
    protected $paymentid;
    /**
     * @var string
     */
    protected $paymoborderid;
    /**
     * @var payable
     */
    protected $payable;
    /**
     * @var \moodle_url
     */
    protected $successurl;
    /**
     * @var int
     */
    protected $userid;
    /**
     * The database table name
     */
    protected const TABLENAME = 'paygw_paymob_orders';

    /**
     * Create a class to manage an order locally
     * @param int $orderid
     */
    public function __construct($orderid) {
        global $DB;
        $this->id = $orderid;
        $record = $DB->get_record(self::TABLENAME, ['id' => $orderid], '*', MUST_EXIST);

        foreach ($record as $key => $value) {
            if ($key == 'pm_orderid') {
                $key = 'paymoborderid';
            } else if ($key == 'payment_id') {
                $key = 'paymentid';
            }

            if (property_exists($this, $key) && !empty($value)) {
                $this->$key = $value;
            }
        }

        try {
            $this->payable = helper::get_payable($this->component, $this->paymentarea, $this->itemid);
    
            $this->rawcost = $this->payable->get_amount();
            $this->currency = $this->payable->get_currency();

        } catch (\dml_missing_record_exception $e) {
            if (!empty($this->paymentid)) {
                $paymentrecord = $DB->get_record('payment', ['id' => $this->paymentid], '*', MUST_EXIST);
                $this->rawcost = $paymentrecord->cost;
                $this->currency = $paymentrecord->currency;
            } else {
                throw $e;
            }
        }

        $surcharge = helper::get_gateway_surcharge('paymob');
        $this->cost = helper::get_rounded_cost($this->rawcost, $this->currency, $surcharge);
    }

    /**
     * Set the paymob order id (intention id)
     * @param string $orderid
     * @param bool $updaterecord
     */
    public function set_pm_orderid($orderid, $updaterecord = true) {
        $this->paymoborderid = $orderid;
        if ($updaterecord) {
            $this->update_record();
        }
    }

    /**
     * Set the payment id
     * @param int $id
     * @param bool $updaterecord
     */
    public function set_paymentid($id, $updaterecord = true) {
        $this->paymentid = $id;
        if ($updaterecord) {
            $this->update_record();
        }
    }

    /**
     * Update the order status
     * @param string $status
     * @param bool $updaterecord
     */
    public function update_status($status, $updaterecord = true) {
        $this->status = $status;
        if ($updaterecord) {
            $this->update_record();
        }
    }

    /**
     * Get the local (merchant) order id
     * which is the same as the table
     * @return int
     */
    public function get_id() {
        return $this->id;
    }
    /**
     * Get paymob orderid.
     * @return int|null
     */
    public function get_pm_orderid() {
        if (empty($this->paymoborderid)) {
            return null;
        } else if (is_number($this->paymoborderid)) {
            return (int)$this->paymoborderid;
        }
        $notes = $this->get_order_notes();
        if (empty($notes)) {
            return null;
        }
        return reset($notes)->paymobid;
    }
    /**
     * Return the intention id.
     * @return string|null
     */
    public function get_intention_id() {
        if (empty($this->paymoborderid)) {
            return null;
        }
        if (!is_number($this->paymoborderid)) {
            $parts = explode('_', $this->paymoborderid);
            if (count($parts) >= 3) {
                return $this->paymoborderid;
            }
        }
        return null;
    }

    /**
     * Get the transaction id
     * There may be many transactions for one order
     * @param int $return -1 the parent transaction, 1 last one, 0 array of all
     * @return int|array[int]|null
     */
    public function get_transaction_id($return = -1) {
        $notes = $this->get_order_notes();
        if (empty($notes)) {
            return null;
        }
        if ($return < 0) {
            return array_pop($notes)->transid;
        } else if ($return > 0) {
            return reset($notes)->transid;
        }
        $ids = [];
        foreach ($notes as $note) {
            $ids[] = $note->transid;
        }
        return $ids;
    }
    /**
     * Return the id of the user.
     * @return int
     */
    public function get_userid() {
        return $this->userid;
    }
    /**
     * Get the user object
     * @return \stdClass
     */
    public function get_user() {
        return \core_user::get_user($this->userid);
    }
    /**
     * Get the currency of this transaction
     * @return string
     */
    public function get_currency() {
        return $this->currency;
    }

    /**
     * Get the cost after adding the surcharge
     * @return float
     */
    public function get_cost() {
        return $this->cost;
    }

    /**
     * Get the raw cost without surcharge.
     * @return float
     */
    public function get_raw_cost() {
        return $this->rawcost;
    }
    /**
     * Get the amount in cents
     * @return float
     */
    public function get_amount_cents() {
        $islegacy = !empty($this->get_gateway_config()->legacy);
        if ($islegacy) {
            $country = 'egy';
        } else {
            $country = utils::get_country_code($this->get_gateway_config()->public_key);
        }

        if ($country == 'omn') {
            $cents = 1000;
        } else {
            $cents = 100;
        }

        return (int)round($this->cost * $cents);
    }
    /**
     * Get the component
     * @return string
     */
    public function get_component() {
        return $this->component;
    }

    /**
     * Get the payment account id for this item
     * @return int
     */
    public function get_account_id() {
        global $DB;
        if (isset($this->payable)) {
            return $this->payable->get_account_id();
        }

        if (!empty($this->paymentid)) {
            return (int)$DB->get_field('payment', 'accountid', ['id' => $this->paymentid]);
        }

        return 0;
    }

    /**
     * Get paymentarea
     * @return string
     */
    public function get_paymentarea() {
        return $this->paymentarea;
    }

    /**
     * Return the itemid
     * @return int
     */
    public function get_itemid() {
        return $this->itemid;
    }

    /**
     * Return the order status.
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Get the payment configurations
     * @return \stdClass
     */
    public function get_gateway_config() {
        $config = (object)helper::get_gateway_configuration($this->component,
                                                            $this->paymentarea,
                                                            $this->itemid,
                                                            'paymob');
        $config->integration_ids = json_decode($config->integration_ids);

        return $config;
    }

    /**
     * Get redirect url
     * @return \moodle_url
     */
    public function get_redirect_url() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        if (!empty($this->successurl)) {
            return $this->successurl;
        }
        // Find redirection.
        $url = new \moodle_url('/');
        // Method only exists in 3.11+.
        if (method_exists('\core_payment\helper', 'get_success_url')) {
            $url = helper::get_success_url($this->component, $this->paymentarea, $this->itemid);
        } else if (($this->component == 'enrol_fee' && $this->paymentarea == 'fee')
                || ($this->component == 'enrol_wallet' && $this->paymentarea == 'enrol')) {
            $enrol = explode('_', $this->component, 2)[1];
            $courseid = $DB->get_field('enrol', 'courseid', ['enrol' => $enrol, 'id' => $this->itemid]);
            if (!empty($courseid)) {
                $url = course_get_url($courseid);
            }
        }
        $this->successurl = $url;
        return $this->successurl;
    }
    /**
     * Verify than we can change the order status
     * @param bool $throw throw error on fail
     * @param string $status callback status
     * @return bool
     */
    public function verify_order_changeable($throw = true, $status = '') {

        // Success only change when void or refund the transaction.
        if (!empty($status)) {
            if ($this->status != $status && in_array($status, ['void', 'voided', 'refund', 'refunded'])) {
                return true;
            }
        }

        // Don't change success, voided or refunded.
        $changeable = ['pending', 'failed', 'on-hold', 'intended', 'processing'];
        if (!in_array($this->status, $changeable) || $this->status == $status) {
            if ($throw) {
                if ($status === 'success') {
                    die("Cannot change order status from $this->status .' to '. $status");
                }
                throw new \moodle_exception('order_unchangeable', 'paygw_paymob', '', $this->status .' to '. $status);
            }
            return false;
        }
        return true;
    }

    /**
     * Save the payment and process the order
     * This will automatically update the record.
     */
    public function payment_complete() {
        $paymentid = helper::save_payment($this->get_account_id(),
                                $this->component,
                                $this->paymentarea,
                                $this->itemid,
                                $this->userid,
                                $this->cost,
                                $this->currency,
                                'paymob');

        $this->update_status('success', false);
        $this->set_paymentid($paymentid);

        helper::deliver_order($this->component,
                              $this->paymentarea,
                              $this->itemid,
                              $paymentid,
                              $this->userid);

        requester::log('order delivered');
        // Notify user.
        notifications::notify($this, 'success');

        requester::log('order notified');
    }

    /**
     * Add notes for the current order for logging purpose
     * @param int $integrationid the payment integration id used
     * @param string $type The type of integration (card, wallet, ..)
     * @param string $subtype The subtype (mastercard, visa, ...)
     * @param int $transid The transaction id
     * @param int $paymobid Paymob order id
     * @param string $extra The data message received (Approved, Declined, ...)
     * @return int
     */
    public function add_order_note_data($integrationid, $type, $subtype, $transid, $paymobid, $extra = '') {
        global $DB, $OUTPUT;

        $data = [
            'orderid'       => $this->id,
            'integrationid' => $integrationid,
            'type'          => $type,
            'subtype'       => $subtype,
            'transid'       => $transid,
            'paymobid'      => $paymobid,
            'extra'         => $extra,
            'timecreated'   => time(),
        ];
        return $DB->insert_record('paygw_paymob_order_notes', $data);
    }

    /**
     * Same as add_order_note_data but only pass the array of transaction
     * data
     * @param array $obj
     * @return int
     */
    public function add_note_from_transaction($obj) {
        $obj = (array)$obj;
        $obj['source_data'] = (array)$obj['source_data'];
        $obj['order'] = (array)$obj['order'];

        $integrationid = $obj['integration_id'];
        $type          = $obj['source_data']['type'];
        $subtype       = $obj['source_data']['sub_type'];
        $transid       = $obj['id'];
        $paymobid      = $obj['order']['id'];
        $extra         = $obj['data_message'] ?? $obj['message'] ?? '';
        return $this->add_order_note_data($integrationid, $type, $subtype, $transid, $paymobid, $extra);
    }
    /**
     * Get all notes for this order
     * @return array[\stdClass]
     */
    public function get_order_notes() {
        global $DB;
        return $DB->get_records('paygw_paymob_order_notes', ['orderid' => $this->id], 'id DESC');
    }

    /**
     * Get all orders notes and append to each note a property 'html'
     * which is the formatted note
     * @return array[\stdClass]
     */
    public function get_order_notes_html() {
        global $OUTPUT;
        $notes = $this->get_order_notes();
        $url = utils::get_api_url($this->get_gateway_config()->public_key ?? '');
        foreach ($notes as $note) {
            $tempdata = [
                'integrationid'   => $note->integrationid,
                'type'            => $note->type,
                'subtype'         => $note->subtype,
                'transaction'     => $note->transid,
                'paymobid'        => $note->paymobid,
                'responsemessage' => $note->extra ?? '',
                'url'             => $url,
            ];
            $note->html = $OUTPUT->render_from_template('paygw_paymob/order_note', $tempdata);
        }
        return $notes;
    }
    /**
     * Update the data base record.
     */
    protected function update_record() {
        global $DB;

        $this->timemodified = time();
        $record = [
            'id'           => $this->id,
            'payment_id'   => $this->paymentid ?? null,
            'pm_orderid'   => $this->paymoborderid ?? null,
            'status'       => $this->status,
            'timemodified' => time(),
        ];
        $DB->update_record(self::TABLENAME, (object)$record);
    }
    /**
     * Create a new order.
     * @param string $component
     * @param string $paymentarea
     * @param string $itemid
     */
    public static function created_order($component, $paymentarea, $itemid) {
        global $USER, $DB;
        // Try to get an order with the same data in the last 15 min.
        $data = [
            'itemid'       => $itemid,
            'component'    => $component,
            'paymentarea'  => $paymentarea,
            'userid'       => $USER->id,
            'status'       => 'new',
            'timetocheck'  => time() - 15 * MINSECS,
        ];
        $select = "itemid = :itemid AND component = :component AND paymentarea = :paymentarea";
        $select .= " AND userid = :userid AND status = :status AND timecreated >= :timetocheck";
        $records = $DB->get_records_select(self::TABLENAME, $select, $data, 'timecreated DESC', 'id', 0, 1);
        if (!empty($records)) {
            return new order(reset($records)->id);
        }

        // Create a new one.
        unset($data['timetocheck']);
        $data['timecreated'] = $data['timemodified'] = time();
        $orderid = $DB->insert_record(self::TABLENAME, (object)$data);
        return new order($orderid);
    }

    /**
     * Make instance of order management class by passing the paymob
     * order id.
     * @param int $pmorderid
     * @return order
     */
    public static function instance_form_pm_orderid($pmorderid) {
        global $DB;
        $record = $DB->get_record(self::TABLENAME, ['pm_orderid' => $pmorderid], 'id', MUST_EXIST);
        return new order($record->id);
    }
    /**
     * Get all orders
     * @param int $from
     * @param int $to
     * @return array[order]
     */
    public static function get_orders($from = 0, $to = 0) {
        global $DB;
        $select = "1=1";
        $params = [];
        if ($from > 0) {
            $select .= " AND timecreated >= :fromtime";
            $params['fromtime'] = $from;
        }
        if ($to > 0) {
            $select .= " AND timecreated <= :totime";
            $params['totime'] = $to;
        }
        $records = $DB->get_records_select(self::TABLENAME, $select, $params, '', 'id');
        $orders = [];
        foreach ($records as $record) {
            $orders[$record->id] = new order($record->id);
        }
        return $orders;
    }
}
