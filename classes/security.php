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
 * Class security
 *
 * Security methods for paymob transactions.
 *
 * @package    paygw_paymob
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class security {
    /**
     * Filter the GLOBAL variables and clean it up.
     *
     * @param string $name The field name the need to be filter.
     * @param string $global value could be (GET, POST, REQUEST, COOKIE, SERVER).
     *
     * @return string|null
     */
    public static function filter_var($name, $global = 'GET') {
        $globals = [
            'GET'     => $_GET,
            'POST'    => $_POST,
            'SERVER'  => $_SERVER,
            'REQUEST' => $_REQUEST,
        ];

        $global = strtoupper($global);
        if (isset($globals[$global]) && isset($globals[$global][$name])) {
            return self::clean_param($name, $globals[$global][$name]);
        }
        return null;
    }
    /**
     * Clean parameter which accepts object or arrays
     * @param string $param the param name
     * @param mixed $value the value
     * @return mixed
     */
    public static function clean_param($param, $value) {
        $isobject = is_object($value);
        $isarray = is_array($value);

        if ($isarray || $isobject) {
            $return = [];
            foreach ($value as $key => $val) {
                $return[$key] = self::clean_param($key, $val);
            }
            if ($isobject) {
                $return = (object)$return;
            }
            return $return;
        }

        if (is_bool($value) || is_null($value) || is_int($value) || is_float($value)) {
            // No cleanup needed.
            return $value;
        }

        $type = PARAM_TEXT;
        $bool = ['success', 'error_occured', 'has_parent_transaction', 'pending', 'lock_order_when_paid'];
        $integers = [
            'id', 'paid_amount_cents', 'gateway_integration_pk',
            'order', 'amount_cents', 'exp', 'owner',
        ];

        if ((strpos($param, 'is_') === 0) || in_array($param, $bool)) {
            $type = PARAM_BOOL;
        } else if (in_array($param, $integers) || (substr($param, -3) === '_id')) {
            if (strlen($value) > 11 && is_number(substr($value, 0, -11))) {
                // Merchant id.
                $type = PARAM_ALPHANUMEXT;
            } else if (count(explode('_', $value)) === 3) {
                // Intention id.
                $type = PARAM_ALPHANUMEXT;
            } else {
                $type = PARAM_INT;
            }
        }

        return clean_param($value, $type);
    }


    /**
     * Verify the hmac hash.
     * @param string $key
     * @param array $data
     * @param array|null $intention
     * @param string $hmac the hashed hmac returned from the request
     */
    public static function verify_hmac($key, $data, $intention = null, $hmac = null) {
        if (isset($hmac)) {
            return self::verify_accept_hmac($key, $data, $hmac);
        } else {
            return self::verify_flash_hmac($key, $data, $intention);
        }
    }

    /**
     * Verify flash hmac
     * @param string $key
     * @param array $data
     * @param array|null $intention
     * @return bool
     */
    private static function verify_flash_hmac($key, $data, $intention = null) {

        if (empty($intention)) {
            // Callback GET.
            $str    = $data['amount_cents']
                    . $data['created_at']
                    . $data['currency']
                    . $data['error_occured']
                    . $data['has_parent_transaction']
                    . $data['id']
                    . $data['integration_id']
                    . $data['is_3d_secure']
                    . $data['is_auth']
                    . $data['is_capture']
                    . $data['is_refunded']
                    . $data['is_standalone_payment']
                    . $data['is_voided']
                    . $data['order']
                    . $data['owner']
                    . $data['pending']
                    . $data['source_data_pan']
                    . $data['source_data_sub_type']
                    . $data['source_data_type']
                    . $data['success'];
            $hash = hash_hmac('sha512', $str, $key);
        } else {
            // Webhook POST.
            $amount = $intention['amount'] / $intention['cents'];
            if (is_float($amount)) {
                $amountarr = explode('.', $amount);
                if (strlen($amountarr[1]) == 1) {
                    $amount = $amount . '0';
                }
            } else {
                $amount = $amount . '.00';
            }

            $str  = $amount . $intention['id'];
            $hash = hash_hmac('sha512', $str, $key, false);
        }

        $hmac = $data['hmac'];

        return $hmac === $hash;
    }

    /**
     * Verify accept hmac
     * @param string $key
     * @param array $jsondata
     * @param string $hmac
     * @return bool
     */
    private static function verify_accept_hmac($key, $jsondata, $hmac) {
        $data                           = $jsondata['obj'];
        $data['order']                  = $data['order']['id'];
        $data['is_3d_secure']           = utils::bool_to_string($data['is_3d_secure']);
        $data['is_auth']                = utils::bool_to_string($data['is_auth']);
        $data['is_capture']             = utils::bool_to_string($data['is_capture']);
        $data['is_refunded']            = utils::bool_to_string($data['is_refunded']);
        $data['is_standalone_payment']  = utils::bool_to_string($data['is_standalone_payment']);
        $data['is_voided']              = utils::bool_to_string($data['is_voided']);
        $data['success']                = utils::bool_to_string($data['success']);
        $data['error_occured']          = utils::bool_to_string($data['error_occured']);
        $data['has_parent_transaction'] = utils::bool_to_string($data['has_parent_transaction']);
        $data['pending']                = utils::bool_to_string($data['pending']);
        $data['source_data_pan']        = $data['source_data']['pan'];
        $data['source_data_type']       = $data['source_data']['type'];
        $data['source_data_sub_type']   = $data['source_data']['sub_type'];

        $str  = '';
        $str  = $data['amount_cents'] .
                $data['created_at'] .
                $data['currency'] .
                $data['error_occured'] .
                $data['has_parent_transaction'] .
                $data['id'] .
                $data['integration_id'] .
                $data['is_3d_secure'] .
                $data['is_auth'] .
                $data['is_capture'] .
                $data['is_refunded'] .
                $data['is_standalone_payment'] .
                $data['is_voided'] .
                $data['order'] .
                $data['owner'] .
                $data['pending'] .
                $data['source_data_pan'] .
                $data['source_data_sub_type'] .
                $data['source_data_type'] .
                $data['success'];
        $hash = hash_hmac('sha512', $str, $key);
        return $hash === $hmac;
    }

    /**
     * Get an array of strings to be concatenated
     * to be used in hmac calculation for legacy api
     * @param array $data
     * @return array[string]
     */
    private static function get_legacy_data_for_hmac($data) {
        $string = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Prepare the string for hmac hash calculation.
            $string = $data['obj']; // Same as $obj in this kind of request.
            $type = $data['type'];
            if ($type === 'TRANSACTION') {
                // Decoding string.
                $string['order']                  = $string['order']['id'];
                $string['is_3d_secure']           = ($string['is_3d_secure'] === true) ? 'true' : 'false';
                $string['is_auth']                = ($string['is_auth'] === true) ? 'true' : 'false';
                $string['is_capture']             = ($string['is_capture'] === true) ? 'true' : 'false';
                $string['is_refunded']            = ($string['is_refunded'] === true) ? 'true' : 'false';
                $string['is_standalone_payment']  = ($string['is_standalone_payment'] === true) ? 'true' : 'false';
                $string['is_voided']              = ($string['is_voided'] === true) ? 'true' : 'false';
                $string['success']                = ($string['success'] === true) ? 'true' : 'false';
                $string['error_occured']          = ($string['error_occured'] === true) ? 'true' : 'false';
                $string['has_parent_transaction'] = ($string['has_parent_transaction'] === true) ? 'true' : 'false';
                $string['pending']                = ($string['pending'] === true) ? 'true' : 'false';
                $string['source_data_pan']        = $string['source_data']['pan'];
                $string['source_data_type']       = $string['source_data']['type'];
                $string['source_data_sub_type']   = $string['source_data']['sub_type'];
            } else if ($type === 'DELIVERY_STATUS') {
                $string['order'] = $string['order']['id'];
            }

        } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {

            // We only need this here for checking the security hmac.
            // So we must use the variable $_GET as it is.
            foreach ($data as $k => $v) {
                $string[$k] = clean_param($v, PARAM_TEXT);
            }
        }
        return $string;
    }
    /**
     * Creating a hash code and then matching it with the hmac key
     * from the called back data for security connection.
     * @param string $key secret key
     * @param array $data
     * @param string $type
     * @return string
     */
    public static function verify_legacy_hmac($key, $data, $type) {
        $data = self::get_legacy_data_for_hmac($data);
        $str = '';
        // Formating the string according to the typw or transaction callback.
        switch ($type) {
            case 'TRANSACTION':
                $str =
                    $data['amount_cents'] .
                    $data['created_at'] .
                    $data['currency'] .
                    $data['error_occured'] .
                    $data['has_parent_transaction'] .
                    $data['id'] .
                    $data['integration_id'] .
                    $data['is_3d_secure'] .
                    $data['is_auth'] .
                    $data['is_capture'] .
                    $data['is_refunded'] .
                    $data['is_standalone_payment'] .
                    $data['is_voided'] .
                    $data['order'] .
                    $data['owner'] .
                    $data['pending'] .
                    $data['source_data_pan'] .
                    $data['source_data_sub_type'] .
                    $data['source_data_type'] .
                    $data['success'];
                break;
            case 'TOKEN':
                $str =
                    $data['card_subtype'] .
                    $data['created_at'] .
                    $data['email'] .
                    $data['id'] .
                    $data['masked_pan'] .
                    $data['merchant_id'] .
                    $data['order_id'] .
                    $data['token'];
                break;
            case 'DELIVERY_STATUS':
                $str =
                    $data['created_at'] .
                    $data['extra_description'] .
                    $data['gps_lat'] .
                    $data['gps_long'] .
                    $data['id'] .
                    $data['merchant'] .
                    $data['order'] .
                    $data['status'];
                break;
        }

        $hash = hash_hmac('sha512', $str, $key);
        // Check the hmac code from request.
        $hmac = optional_param('hmac', '', PARAM_TEXT);
        return $hash === $hmac;
    }
}
