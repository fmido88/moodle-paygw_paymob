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

namespace paygw_paymob\ajax;
use paygw_paymob\payment;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');
/**
 * Class transaction
 *
 * @package    paygw_paymob
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transaction extends \external_api {

    /**
     * Parameters description for get_payment_url()
     * @return \external_description
     */
    public static function get_payment_url_parameters() {
        return new \external_function_parameters([
            'component'   => new \external_value(PARAM_COMPONENT, 'The component in frankstyle'),
            'paymentarea' => new \external_value(PARAM_ALPHANUMEXT, 'The payment area'),
            'itemid'      => new \external_value(PARAM_INT, 'The itemid'),
            'description' => new \external_value(PARAM_TEXT, 'The description of the item the user paying for'),
        ]);
    }
    /**
     * Get the payment url for this order
     * @param string $component
     * @param string $paymentarea
     * @param string $itemid
     * @param string $description
     * @return array
     */
    public static function get_payment_url($component, $paymentarea, $itemid, $description) {
        global $CFG;
        $params = [
            'component'   => $component,
            'paymentarea' => $paymentarea,
            'itemid'      => $itemid,
            'description' => $description,
        ];
        $params = self::validate_parameters(self::get_payment_url_parameters(), $params);

        require_login(null, false);

        try {
            $payment = new payment($params['component'], $params['paymentarea'], $params['itemid'], $params['description']);
            $data = $payment->get_intention_url();
        } catch (\moodle_exception $e) {
            $error = $e->getCode() . "\n" . $e->getMessage();
            if ((int)$CFG->debug & DEBUG_DEVELOPER === DEBUG_DEVELOPER) {
                $error .= "\n" . $e->getTraceAsString();
            }
            $data = [
                'success' => false,
                'error'   => $error,
            ];
        }

        return $data;
    }
    /**
     * Returned data from function get_payment_url()
     * @return \external_description
     */
    public static function get_payment_url_returns() {
        return new \external_single_structure([
            'url' => new \external_value(PARAM_URL, 'payment url', VALUE_OPTIONAL),
            'success' => new \external_value(PARAM_BOOL, 'Is returning url is successful'),
            'error' => new \external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
        ]);
    }
}
