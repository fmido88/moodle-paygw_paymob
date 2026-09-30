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

namespace paygw_paymob\table;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * Orders reports.
 *
 * @package    paygw_paymob
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orders extends \table_sql {
    /**
     * Construct orders table
     * @param string|\moodle_url $baseurl
     */
    public function __construct($baseurl) {
        parent::__construct('paymob_orders');

        $this->is_downloadable(true);
        $this->is_downloading(optional_param('download', "", PARAM_ALPHA), 'paymob_report_' . time());
        $this->show_download_buttons_at([TABLE_P_BOTTOM]);

        $columns = [
            'id'           => get_string('localorderid', 'paygw_paymob'),
            'fullname'     => get_string('user'),
            'itemid'       => get_string('itemid', 'paygw_paymob'),
            'paymentarea'  => get_string('paymentarea', 'paygw_paymob'),
            'component'    => get_string('component', 'paygw_paymob'),
            'payment_id'   => get_string('paymentid', 'paygw_paymob'),
            'amount'       => get_string('amount', 'paygw_paymob'),
            'currency'     => get_string('currency'),
            'status'       => get_string('status'),
            'notes'        => get_string('notes', 'notes'),
            'timecreated'  => get_string('timecreated'),
            'timemodified' => get_string('timemodified', 'paygw_paymob'),
        ];

        if (!$this->is_downloading()) {
            $columns['inquiry'] = get_string('inquiry', 'paygw_paymob');
            $this->no_sorting('inquiry');
            if (has_capability('paygw/paymob:void_refund', \context_system::instance())) {
                $columns['void']    = get_string('void', 'paygw_paymob');
                $columns['refund']  = get_string('refund', 'paygw_paymob');
                $this->no_sorting('void');
                $this->no_sorting('refund');
            }
        }
        $this->define_baseurl($baseurl);
        $this->define_columns(array_keys($columns));
        $this->no_sorting('amount');
        $this->no_sorting('currency');
        $this->no_sorting('notes');

        $this->define_headers(array_values($columns));
        $this->set_our_sql();
    }

    /**
     * Set our proper sql.
     * @return void
     */
    public function set_our_sql() {
        $ufieldsapi = \core_user\fields::for_name();
        $ufields = $ufieldsapi->get_sql('u')->selects;
        $fields = "ord.* $ufields";
        $from = "{paygw_paymob_orders} ord";
        $from .= " JOIN {user} u ON u.id = ord.userid";
        $where = 'ord.status != :new';
        $params = ['new' => 'new'];
        $this->set_sql($fields, $from, $where, $params);
    }

    /**
     * Override to add order object.
     * @param int $pagesize
     * @param bool $useinitialsbar
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;
        parent::query_db($pagesize, $useinitialsbar);
        foreach ($this->rawdata as $key => $record) {
            $this->rawdata[$key]->hasnote = $DB->record_exists('paygw_paymob_order_notes', ['orderid' => $record->id]);
            try {
                $this->rawdata[$key]->order = new \paygw_paymob\order($record->id);
            } catch (\dml_missing_record_exception $e) {
                continue;
            }
        }
    }

    /**
     * Render the orders notes
     * @param object $row
     * @return string
     */
    public function col_notes($row) {
        if (empty($row->order)) {
            return '';
        }
        $order = $row->order;
        if (!$this->is_downloading()) {
            $notes = $order->get_order_notes_html();
            $out = [];
            foreach ($notes as $note) {
                $out[] = $note->html;
            }
            return implode('<br><br>', $out);
        } else {
            $notes = $order->get_order_notes();
            $out = '';
            foreach ($notes as $note) {
                foreach ($note as $key => $value) {
                    $out .= $key . ': ' . $value . '  ';
                }
                $out .= ' _ ';
            }
            return $out;
        }

    }
    /**
     * Render the inquiry button
     * @param \stdClass $row
     * @return string
     */
    public function col_inquiry($row) {
        global $DB;
        if (!$row->hasnote) {
            return '';
        }
        $attributes = [
            'data-action'  => 'inquiry',
            'data-orderid' => $row->id,
            'class'        => 'btn btn-secondary',
        ];
        return \html_writer::tag('button', get_string('inquiry', 'paygw_paymob'), $attributes);
    }
    /**
     * Render void button
     * @param \stdClass $row
     * @return string
     */
    public function col_void($row) {
        // There is no voiding after 24 Hours.
        if ($row->timecreated <= time() - 23 * HOURSECS) {
            return '';
        }

        // The order no successful to be voided.
        if (!$row->hasnote || $row->status !== 'success') {
            return '';
        }

        $attributes = [
            'data-action'  => 'void',
            'data-orderid' => $row->id,
            'class'        => 'btn btn-secondary',
        ];
        return \html_writer::tag('button', get_string('void', 'paygw_paymob'), $attributes);
    }

    /**
     * render refund button.
     * @param \stdClass $row
     * @return string
     */
    public function col_refund($row) {
        if (!$row->hasnote || $row->status !== 'success') {
            return '';
        }
        $attributes = [
            'data-action'  => 'refund',
            'data-orderid' => $row->id,
            'class'        => 'btn btn-secondary',
        ];
        return \html_writer::tag('button', get_string('refund', 'paygw_paymob'), $attributes);
    }
    /**
     * Amount
     * @param object $row
     * @return string
     */
    public function col_amount($row) {
        if (empty($row->order)) {
            return '';
        }
        $order = $row->order;
        return $order->get_cost();
    }
    /**
     * Currency
     * @param object $row
     * @return string
     */
    public function col_currency($row) {
        if (empty($row->order)) {
            return '';
        }
        $order = $row->order;
        return $order->get_currency();
    }

    /**
     * Other columns
     * @param string $column
     * @param object $row
     * @return string
     */
    public function other_cols($column, $row) {
        if (in_array($column, ['timecreated', 'timemodified'])) {
            return userdate($row->$column);
        }
        return $row->$column;
    }
}
