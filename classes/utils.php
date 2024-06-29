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
 * Class utils
 *
 * @package    paygw_paymob
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {

    /**
     * Parse array of integration ids from stored string
     * @param string $integrationids
     * @param bool $returndata if true the function return array or arrays of detailed data
     *                         for each integration id
     *                         else it return array of strings to be uses as options for integrations
     * @return array
     */
    public static function get_integration_ids_from_string($integrationids, $returndata = false) {
        $output = [];
        $integrationids = explode(',' , $integrationids);
        foreach ($integrationids as $entry) {
            $parts = explode( ':', $entry );
            // Parts in order " id : name (type : currency )".
            $id = (int)trim($parts[0]);
            if (isset($parts[2]) && $id > 0) {
                if ($returndata) {
                    $tyname = explode('(', $parts[1]);
                    $type = array_pop($tyname);

                    $name = implode('(', $tyname);
                    $name = trim(reset($tyname) ?? 'null');

                    if (empty($name) || strtolower($name) === 'null') {
                        $name = '';
                    }

                    $output[$id] = (object)[
                        'id'       => $parts[0],
                        'type'     => trim($type),
                        'name'     => $name,
                        'currency' => trim(substr($parts[2], strpos($parts[2], '(') + 1, -2)),
                    ];
                } else {
                    $output[$id] = $entry;
                }
            }
        }
        return $output;
    }

    /**
     * Get the enabled payment methods
     * @param \stdClass $config the payment gateway configuration
     * @param string $currency
     * @return array[int] keyed by method type
     */
    public static function get_payment_methods($config, $currency) {
        $all = $config->integration_ids_hidden;
        $integrations = self::get_integration_ids_from_string($all, true);

        $ids = $config->integration_ids;

        $out = [];
        $types = ['card', 'wallet', 'aman'];
        foreach ($integrations as $key => $object) {
            if (!in_array($key, $ids)
                || $object->currency !== $currency) {
                unset($integrations[$key]);
            }

            $type = strtolower($object->type);
            if (in_array($type, $types)) {
                $out[$type] = $object->id;
            }
        }
        return $out;
    }
    /**
     * Implode the integration ids to string to be saved in the
     * configuration.
     * @param array $integrationids
     * @return string
     */
    public static function integration_ids_to_string($integrationids) {
        $string = '';
        foreach ($integrationids as $entry) {
            $whole = implode(':', $entry);
            $string .= $whole . ',';
        }
        return $string;
    }

    /**
     * Get the country code from the key
     * @param string $code
     * @return string
     */
    public static function get_country_code($code) {
        return (string)substr($code, 0, 3);
    }

    /**
     * Return true or false strings from a boolean value
     * @param bool $value
     * @return string
     */
    public static function bool_to_string($value) {
        $value = clean_param($value, PARAM_BOOL);
        if ($value) {
            return 'true';
        }
        return 'false';
    }
    /**
     * Get the api url from private or public key
     * @param string $key
     * @param bool $throw throw exception if cannot get the url
     */
    public static function get_api_url($key, $throw = false) {
        $countrycode = self::get_country_code($key);
        $domain = 'paymob.com';
        switch ($countrycode) {
            case 'are':
            case 'uae':
                $apiurl = 'https://uae.' . $domain;
                break;
            case 'eg':
            case 'egy':
                $apiurl = 'https://accept.' . $domain;
                break;
            case 'pak':
                $apiurl = 'https://paymob.com.pk';
                break;
            case 'ksa':
            case 'sau':
                $apiurl = 'https://ksa.' . $domain;
                break;
            case 'omn':
                $apiurl = 'https://oman.' . $domain;
                break;
            default:
                if (!$throw) {
                    return false;
                }
                throw new \moodle_exception('unsupportedcountry', 'paygw_paymob');
        }
        return $apiurl;
    }

    /**
     * Match modes (live or test)
     * @param string $publickey
     * @param string $privatekey
     * @return bool
     */
    public static function match_mode($publickey, $privatekey) {
        $pubkeymode = self::get_mode($publickey);
        $seckeymode = self::get_mode($privatekey);

        if ($seckeymode !== $pubkeymode) {
            return false;
        }
        return true;
    }

    /**
     * Check if the mode is live or not.
     * @param string $key
     * @return bool
     */
    public static function is_live($key) {
        $mode = self::get_mode($key);
        if (empty($mode)) {
            return -1;
        }
        return $mode === 'live';
    }

    /**
     * Get the mode from the key
     * @param string $code
     * @return string live or test
     */
    public static function get_mode($code) {
        return (string)substr($code, 7, 4);
    }

    /**
     * Check if the keys provided are corresponding
     * to the given type.
     * @param string $key private or public key
     * @param string $type 'private' or 'public'
     * @return bool
     */
    public static function verify_key($key, $type) {
        if (strlen($key) < 20) {
            return false;
        }

        $parts = explode('_', $key);

        if (count($parts) !== 4
            || strlen($parts[0]) !== 3
            || strlen($parts[1]) !== 2
            || strlen($parts[2]) !== 4) {
            return false;
        }

        if (!in_array($parts[2], ['live', 'test'])) {
            return false;
        }

        $key = (string)$parts[1];
        $type = strtolower($type);
        if (strstr($type, 'pub') && $key == 'pk') {
            return true;
        }

        if (strstr($type, 'sec') || strstr($type, 'private')) {
            if ($key == 'sk') {
                return true;
            }
        }

        return false;
    }
    /**
     * Check if the countries where matched in both of
     * public and private key
     * @param string $privatekey
     * @param string $publickey
     * @return bool
     */
    public static function match_countries($privatekey, $publickey) {
        $pubkey = self::get_country_code($publickey);
        $seckey = self::get_country_code($privatekey);
        if ($pubkey !== $seckey) {
            return false;
        }
        return true;
    }

    /**
     * Get the the order status from the transaction object.
     * @param array $obj
     * @return string
     */
    public static function get_order_status($obj) {
        $success  = $obj['success'] ?? false;
        $voided   = $obj['is_voided'] ?? false;
        $refunded = $obj['is_refunded'] ?? false;
        $pending  = $obj['pending'] ?? false;
        $void     = $obj['is_void'] ?? false;
        $refund   = $obj['is_refund'] ?? false;
        $error    = $obj['error_occured'] ?? false;
        $auth     = $obj['is_auth'] ?? false;
        $capture  = $obj['is_capture'] ?? false;

        if ($success && !$error) {
            if (!$voided && !$refunded && !$pending && !$void && !$refund) {
                return 'success';
            }
            if ($voided && !$refunded && !$pending && !$void && !$refund) {
                return 'voided';
            }
            if (!$voided && $refunded && !$pending && !$void && !$refund) {
                return 'refunded';
            }
            if (!$voided && !$refunded && $pending && !$void && !$refund) {
                return 'pending';
            }
            if (!$voided && !$refunded && !$pending && $void && !$refund) {
                return 'void';
            }
            if (!$voided && !$refunded && !$pending && !$void && $refund) {
                return 'refund';
            }
        }
        return 'failed';
    }

    /**
     * Get the order id from the response.
     * @param string $merchantintentionid
     * @return int
     */
    public static function extract_order_id($merchantintentionid) {
        if (is_number($merchantintentionid)) {
            return (int)$merchantintentionid;
        }
        // Sent as special reference {orderid}_{timestamp}.
        return (int)substr($merchantintentionid, 0, -11);
    }
    /**
     * Get the time zone from the county code.
     * @param string $country
     * @return string
     */
    public static function get_time_zone($country) {
        switch ( $country ) {
            case 'omn':
                return 'Asia/Muscat';
            case 'pak':
                return 'Asia/Karachi';
            case 'ksa':
            case 'sau':
                return 'Asia/Riyadh';
            case 'are':
            case 'uae':
                return 'Asia/Dubai';
            case 'egy':
            default:
                return 'Africa/Cairo';
        }
    }
}
