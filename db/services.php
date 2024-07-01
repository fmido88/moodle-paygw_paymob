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
 * External functions and service declaration for Paymob payment
 *
 * Documentation: {@link https://moodledev.io/docs/apis/subsystems/external/description}
 *
 * @package    paygw_paymob
 * @category   webservice
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_paymob_get_admin_options' => [
        'classname'   => 'paygw_paymob\ajax\admin',
        'methodname'  => 'get_admin_options',
        'classpath'   => 'payment/gateway/paymob/classes/ajax/admin.php',
        'description' => 'Authenticate with pay mob and return the integration ids options',
        'type'        => 'write',
        'services'    => [],
        'ajax'        => true,
    ],
    'paygw_paymob_get_payment_url' => [
        'classname'   => 'paygw_paymob\ajax\transaction',
        'methodname'  => 'get_payment_url',
        'classpath'   => 'payment/gateway/paymob/classes/ajax/transaction.php',
        'description' => 'Get the payment url',
        'type'        => 'write',
        'services'    => [],
        'ajax'        => true,
    ],
    'paygw_paymob_inquiry' => [
        'classname'   => 'paygw_paymob\ajax\transaction',
        'methodname'  => 'get_inquiry',
        'classpath'   => 'payment/gateway/paymob/classes/ajax/transaction.php',
        'description' => 'Get the payment url',
        'type'        => 'write',
        'services'    => [],
        'ajax'        => true,
    ],
    'paygw_paymob_void' => [
        'classname'   => 'paygw_paymob\ajax\transaction',
        'methodname'  => 'void_transaction',
        'classpath'   => 'payment/gateway/paymob/classes/ajax/transaction.php',
        'description' => 'Get the payment url',
        'type'        => 'write',
        'services'    => [],
        'ajax'        => true,
    ],
    'paygw_paymob_refund' => [
        'classname'   => 'paygw_paymob\ajax\transaction',
        'methodname'  => 'refund_transaction',
        'classpath'   => 'payment/gateway/paymob/classes/ajax/transaction.php',
        'description' => 'Get the payment url',
        'type'        => 'write',
        'services'    => [],
        'ajax'        => true,
    ],
];

