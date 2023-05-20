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
 * Contains helper class to work with paymob REST API.
 *
 * @package    paygw_paymob
 * @copyright  2023 Mo. Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_paymob;


/**
 * All function needed to perform an API workflow with Paymob.
 */
class paymob_helper {
    /**
     * The API key.
     * @var string
     */
    private $apikey;
    /**
     * The authtoken from the request.
     * @var string
     */
    private $authtoken;
    /**
     * The paymob host url at which we send requests.
     */
    const HOST = "https://accept.paymobsolutions.com/api/";

    /**
     * Constructor.
     * @param string $apikey
     */
    public function __construct($apikey) {
        $this->apikey = $apikey;
        $this->authtoken = $this->request_token($apikey);
    }

    /**
     * Function to perform requests to paymob endpoint.
     * @param string $urlpath
     * @param array $data
     * @return object|string object of returned data or string on error.
     */
    private function http_post($urlpath, $data = []) {

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => self::HOST.$urlpath,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
            ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $responserawdata = json_decode($response, false);

        if ($httpcode != 200 && $httpcode != 201 ) {
            // Endpoint returned an error.
            return "endpoint return error";
        }

        curl_close($curl);
        return $responserawdata;
    }

    /**
     * First step of paymob API workflow is to request auth token.
     * @param string $apikey
     * @return string|false the token or false on error.
     */
    public function request_token($apikey) {
        $data = [
            'api_key' => $apikey
        ];

        $request = self::http_post('auth/tokens', $data);

        if ($request && isset($request->token)) {
            return $request->token;
        }

        return false;
    }

    /**
     * Second step in paymob API workflow is to register an order.
     * This function requesting order on paymob endpoint and return the id of the order
     * which we need to perform the payment.
     *
     * @param float $fee
     * @param string $currency
     * @param array $orderitems
     * @return int|false oredr id or false in failer.
     */
    public function request_order($fee, $currency, $orderitems = null) {

        $data = [
            "auth_token" => $this->authtoken,
            "delivery_needed" => "false",
            "amount_cents" => ($fee),
            "currency" => $currency,
            "items" => $orderitems
        ];

        $order = $this->http_post("ecommerce/orders", $data);

        if ($order && isset($order->id)) {
            return $order->id;
        }

        return false;
    }

    /**
     * Third step is Requesting payment key for the final payment step.
     * return the order id and the payment token in stdclass object
     * or false on failer.
     * @param string $itemname
     * @param float $fee
     * @param string $currency
     * @param int $intid
     * @param mixed $savedcard may be string or bool.
     * @return \stdClass|bool
     */
    public function request_payment_key($itemname, $fee, $currency, $intid, $savedcard = false) {
        global $USER, $DB;

        $userphone = '';
        if (!empty($USER->phone1)) {
            $userphone = $USER->phone1;
        } else if (!empty($USER->phone2)) {
            $userphone = $USER->phone2;
        } else {
            // Get the ID of the custom profile field for phone number.
            $phonefieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'phone'));

            // Get the phone number from the user_info_data table using the field ID and user ID.
            $userphone = $DB->get_field('user_info_data', 'data', array('userid' => $USER->id, 'fieldid' => $phonefieldid));
        }

        if (empty($userphone)) {
            $userphone = 01000000000;
        }

        if (!empty($USER->city)) {
            $city = $USER->city;
        } else {
            $city = "NA";
        }

        $useremail = $USER->email;

        $billingdata = [
            "apartment" => "NA",
            "email" => $useremail,
            "floor" => "NA",
            "first_name" => $USER->firstname,
            "street" => "NA",
            "building" => "NA",
            "phone_number" => $userphone,
            "shipping_method" => "NA",
            "postal_code" => "NA",
            "city" => $city,
            "country" => $USER->country,
            "last_name" => $USER->lastname,
            "state" => "NA"
        ];

        $orderitems = [[
            "name" => $itemname,
            "amount_cents" => ($fee),
            "description" => "NA",
            "quantity" => "1"
        ]];

        $token = $this->authtoken;
        $orderid = self::request_order($fee, $currency, $orderitems);
        $data = [
            "auth_token" => $token,
            "amount_cents" => ($fee),
            "expiration" => 36000,
            "order_id" => $orderid,
            "billing_data" => $billingdata,
            "currency" => $currency,
            "integration_id" => $intid,
            "lock_order_when_paid" => true
        ];

        if ($savedcard) {
            $data['token'] = $savedcard;
        }

        $payment = $this->http_post("acceptance/payment_keys", $data);

        if ($payment && isset($payment->token)) {
            $data = new \stdClass;
            $data->pm_orderid = $orderid;
            $data->cost = $fee / 100;
            $data->userid = $USER->id;
            $data->username = $USER->username;
            $data->intid = $intid;
            $DB->insert_record('paygw_paymob', $data);

            $return = new \stdClass;
            $return->orderid = $orderid;
            $return->paytoken = $payment->token;
            return $return;
        }

        return false;
    }

    /**
     * Requesting the Kisok bill reference.
     * @param string $itemname
     * @param float $fee
     * @param string $currency
     * @param int $intid the integration id.
     * @return \stdClass|bool
     */
    public function request_kiosk_id($itemname, $fee, $currency, $intid) {
        $order = self::request_payment_key($itemname, $fee, $currency, $intid, false);
        $data = [
            "source" => [
                "identifier" => "AGGREGATOR",
                "subtype" => "AGGREGATOR"
            ],
            "payment_token" => $order->paytoken,
        ];

        $request = $this->http_post("acceptance/payments/pay", $data);

        if ($request) {
            if (isset($request->pending) && $request->pending) {
                if (isset($request->data->bill_reference)) {
                    $return = new \stdClass;
                    $return->orderid = $request->order->id;
                    $return->method = $request->source_data->type;
                    $return->reference = $request->data->bill_reference;
                    return $return;
                }
            }
        }

        return false;
    }

    /**
     * Request the URL for payment through mobile wallet.
     * @param int $phonenumber
     * @param string $itemname
     * @param float $fee
     * @param string $currency
     * @param int $intid
     * @return \stdClass|bool
     */
    public function request_wallet_url($phonenumber, $itemname, $fee, $currency, $intid) {
        $order = self::request_payment_key($itemname, $fee, $currency, $intid, false);
        $data = [
            "source" => [
                "identifier" => $phonenumber,
                "subtype" => "WALLET",
            ],
            "payment_token" => $order->paytoken,
        ];

        $request = $this->http_post("acceptance/payments/pay", $data);

        if ($request) {
            if (isset($request->redirect_url)) {
                $return = new \stdClass;
                $return->redirecturl = $request->redirect_url;
                $return->iframeurl = $request->iframe_redirection_url;
                $return->orderid = $request->order->id;
                $return->method = $request->source_data->type;
                return $return;
            }
        }

        return false;
    }

    /**
     * Creating a hash code and then matching it with the hmac key
     * from the calledback data for security connection.
     * @param string $key secret key
     * @param array $data
     * @param string $type
     * @return string
     */
    public function hash($key, $data, $type) {
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
        return $hash;
    }
}
