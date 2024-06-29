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

/**
 * Privacy Subsystem implementation for paygw_paymob.
 *
 * @package    paygw_paymob
 * @category   privacy
 * @copyright  2023 Mo. Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_paymob\privacy;

use core_payment\privacy\paygw_provider;
use core_privacy\local\request\writer;
use core_privacy\local\metadata\collection;

/**
 * Privacy Subsystem implementation for paygw_paymob.
 *
 * @copyright  2023 Mo. Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, paygw_provider {

    /**
     * Returns meta data about this system.
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('paygw_paymob', [
                'firstname' => 'privacy:metadata:paygw_paymob:firstname',
                'lastname'  => 'privacy:metadata:paygw_paymob:lastname',
                'country'   => 'privacy:metadata:paygw_paymob:country',
                'city'      => 'privacy:metadata:paygw_paymob:city',
                'phone'     => 'privacy:metadata:paygw_paymob:phone',
                'email'     => 'privacy:metadata:paygw_paymob:email',
        ], 'privacy:metadata:paygw_paymob');

        $collection->add_database_table('paygw_paymob_orders', [
            'userid' => 'privacy:metadata:paygw_paymob_orders:userid',
            'pm_orderid' => 'privacy:metadata:paygw_paymob_orders:pm_orderid',
        ], 'privacy:metadata:paygw_paymob_orders');
        return $collection;
    }
    /**
     * Export all user data for the specified payment record, and the given context.
     *
     * @param \context $context Context
     * @param array $subcontext The location within the current context that the payment data belongs
     * @param \stdClass $payment The payment record
     */
    public static function export_payment_data(\context $context, array $subcontext, \stdClass $payment) {
        global $DB;

        $subcontext[] = get_string('gatewayname', 'paygw_paymob');
        $conditions = [
            'payment_id' => $payment->id,
        ];

        $record = $DB->get_record('paygw_paymob_orders', $conditions);

        $data = (object)[
            'pm_orderid' => $record->pm_orderid,
        ];
        writer::with_context($context)->export_data(
            $subcontext,
            $data
        );
    }

    /**
     * Delete all user data related to the given payments.
     *
     * @param string $paymentsql SQL query that selects payment.id field for the payments
     * @param array $paymentparams Array of parameters for $paymentsql
     */
    public static function delete_data_for_payment_sql(string $paymentsql, array $paymentparams) {
        global $DB;

        $DB->delete_records_select('paygw_paymob_orders', "payment_id IN ({$paymentsql})", $paymentparams);
    }
}
