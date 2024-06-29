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
use paygw_paymob\requester;

/**
 * Class admin
 *
 * @package    paygw_paymob
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin extends \external_api {
    /**
     * Parameters descriptions for get_admin_options()
     * @return \external_description
     */
    public static function get_admin_options_parameters() {
        return new \external_function_parameters(
            [
                'apikey'     => new \external_value(PARAM_TEXT, 'The paymob api key'),
                'privatekey' => new \external_value(PARAM_TEXT, 'The private (secret) key'),
                'publickey'  => new \external_value(PARAM_TEXT, 'The public key'),
                'accountid'  => new \external_value(PARAM_INT, 'the payment account id'),
            ]
        );
    }
    /**
     * Validate api key and other keys and retrieve hmac and integrations
     * @param string $apikey
     * @param string $privatekey
     * @param string $publickey
     * @param int $accountid
     * @return array
     */
    public static function get_admin_options($apikey, $privatekey, $publickey, $accountid) {
        require_admin();
        $params = [
            'apikey'     => $apikey,
            'privatekey' => $privatekey,
            'publickey'  => $publickey,
            'accountid'  => $accountid,
        ];
        $params = self::validate_parameters(self::get_admin_options_parameters(), $params);
        $requester = new requester($params['apikey'], $params['publickey'], $params['privatekey']);

        $integrationids = $requester->get_integration_ids();

        $data = new \stdClass;
        $data->integration_ids_hidden = \paygw_paymob\utils::integration_ids_to_string($integrationids);
        $data->hmac_hidden = $requester->get_hmac();
        $data->accountid = $params['accountid'];
        $data->gateway = 'paymob';

        \core_payment\helper::save_payment_gateway($data);

        return [
            'integration_ids' => json_encode($integrationids),
            'hmac'            => $data->hmac_hidden,
        ];
    }
    /**
     * Returned parameters from get_admin_options()
     * @return \external_description
     */
    public static function get_admin_options_returns() {
        return new \external_single_structure([
                'integration_ids' => new \external_value(PARAM_TEXT, 'Json array of ids'),
                'hmac'            => new \external_value(PARAM_TEXT, 'hmac'),
        ]);
    }
}
