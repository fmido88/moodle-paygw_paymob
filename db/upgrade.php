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
 * Upgrade steps for Paymob payment
 *
 * Documentation: {@link https://moodledev.io/docs/guides/upgrade}
 *
 * @package    paygw_paymob
 * @category   upgrade
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_paygw_paymob_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2024062602) {

        // Define table paygw_paymob_orders to be created.
        $table = new xmldb_table('paygw_paymob_orders');

        // Adding fields to table paygw_paymob_orders.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('paymentarea', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('payment_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('pm_orderid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'new');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table paygw_paymob_orders.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for paygw_paymob_orders.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table paygw_paymob_order_notes to be created.
        $table = new xmldb_table('paygw_paymob_order_notes');

        // Adding fields to table paygw_paymob_order_notes.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('orderid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('subtype', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('transid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('paymobid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('extra', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table paygw_paymob_order_notes.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('orderid', XMLDB_KEY_FOREIGN, ['orderid'], 'paygw_paymob_orders', ['id']);

        // Conditionally launch create table for paygw_paymob_order_notes.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Paymob savepoint reached.
        upgrade_plugin_savepoint(true, 2024062602, 'paygw', 'paymob');
    }
    if ($oldversion < 2024063000) {

        // Define field integrationid to be added to paygw_paymob_order_notes.
        $table = new xmldb_table('paygw_paymob_order_notes');
        $field = new xmldb_field('integrationid', XMLDB_TYPE_INTEGER, '20', null, null, null, null, 'subtype');

        // Conditionally launch add field integrationid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Paymob savepoint reached.
        upgrade_plugin_savepoint(true, 2024063000, 'paygw', 'paymob');
    }
    return true;
}
