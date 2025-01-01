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
 *
 * This now is to be used for legacy API only.
 */
class legacy_requester {
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
     * The order id (not the local one) from paymob request.
     * @var int
     */
    private $orderid;
    /**
     * The payment data from request_payment_key.
     * @var \stdClass
     */
    private $paydata;
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
        if (empty($this->authtoken)) {
            $this->authtoken = $this->request_token($apikey);
        }
    }

    /**
     * Function to perform requests to paymob endpoint.
     * @param string $urlpath
     * @param array $data
     * @return object|string object of returned data or string on error.
     */
    private function http_post($urlpath, $data = []) {
        global $CFG;
        require_once($CFG->libdir."/filelib.php");
        $curl = new \curl();

        $options = [
            'url'            => self::HOST . $urlpath,
            'returntransfer' => true,
            'failonerror'    => false,
            'post'           => true,
            'postfields'     => json_encode($data),
            'httpheader'     => [
                'Content-Type: application/json',
            ],
        ];
        $curl->setopt($options);

        $response = $curl->post(self::HOST.$urlpath, json_encode($data), $options);
        $httpcode = $curl->get_info()['http_code'] ?? 0;
        $responsedata = json_decode($response, false);

        $curl->cleanopt();
        if ($httpcode != 200 && $httpcode != 201 ) {
            // Endpoint returned an error.
            debugging("endpoint return error " . $response);
            return "endpoint return error: " . $response;
        }

        return $responsedata;
    }

    /**
     * First step of paymob API workflow is to request auth token.
     * @param string $apikey
     * @return string|false the token or false on error.
     */
    public function request_token($apikey) {
        $data = [
            'api_key' => $apikey,
        ];

        $request = self::http_post('auth/tokens', $data);

        if ($request && isset($request->token)) {
            return $request->token;
        }

        debugging("Requesting tokken failed" . $request);
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
     * @return int|false order id or false in fail.
     */
    public function request_order($fee, $currency, $orderitems = null) {

        $data = [
            "auth_token"      => $this->authtoken,
            "delivery_needed" => "false",
            "amount_cents"    => ($fee),
            "currency"        => $currency,
            "items"           => [$orderitems],
        ];

        $order = $this->http_post("ecommerce/orders", $data);

        if ($order && isset($order->id)) {
            $this->orderid = $order->id;
            return $order->id;
        }

        debugging("Requesting order failed. " . $order);
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
            foreach ($USER->profile as $field => $data) {
                if (stripos($field, 'phone') !== false) {
                    $userphone = $data;
                    break;
                }
            }
        }

        if (empty($userphone)) {
            $userphone = 01000000000;
        }

        $city = (!empty($USER->city)) ? $USER->city : "NA";

        $billingdata = [
            "apartment"       => "NA",
            "email"           => $USER->email,
            "floor"           => "NA",
            "first_name"      => $USER->firstname,
            "street"          => "NA",
            "building"        => "NA",
            "phone_number"    => $userphone,
            "shipping_method" => "NA",
            "postal_code"     => "NA",
            "city"            => $city,
            "country"         => $USER->country,
            "last_name"       => $USER->lastname,
            "state"           => "NA",
        ];

        $orderitems = [
            "name"         => $itemname,
            "amount_cents" => ($fee),
            "description"  => "NA",
            "quantity"     => "1",
        ];

        $token = $this->authtoken;
        $orderid = (!empty($this->orderid)) ? $this->orderid : self::request_order($fee, $currency, $orderitems);
        if (empty($orderid)) {
            return false;
        }

        $data = [
            "auth_token"           => $token,
            "amount_cents"         => ($fee),
            "expiration"           => 36000,
            "order_id"             => $orderid,
            "billing_data"         => $billingdata,
            "currency"             => $currency,
            "integration_id"       => $intid,
            "lock_order_when_paid" => true,
        ];

        if ($savedcard) {
            $data['token'] = $savedcard;
        }

        $payment = $this->http_post("acceptance/payment_keys", $data);

        if (!empty($payment) && isset($payment->token)) {

            $return = new \stdClass;
            $return->orderid  = $orderid;
            $return->paytoken = $payment->token;
            $this->paydata = $return;
            return $return;
        }
        debugging("Requesting payment key failed. " . $payment);
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
        $order = !empty($this->paydata) ? $this->paydata : self::request_payment_key($itemname, $fee, $currency, $intid, false);
        if (empty($order)) {
            return false;
        }
        $data = [
            "source" => [
                "identifier" => "AGGREGATOR",
                "subtype"    => "AGGREGATOR",
            ],
            "payment_token" => $order->paytoken,
        ];

        $request = $this->http_post("acceptance/payments/pay", $data);

        if ($request) {
            if (isset($request->pending) && $request->pending) {
                if (isset($request->data->bill_reference)) {
                    $return = new \stdClass;
                    $return->orderid   = $request->order->id;
                    $return->method    = $request->source_data->type;
                    $return->reference = $request->data->bill_reference;
                    return $return;
                }
            }
        }
        debugging("Requesting kiosek id faild. " . $request);
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
        $order = !empty($this->paydata) ? $this->paydata : self::request_payment_key($itemname, $fee, $currency, $intid, false);
        if (empty($order)) {
            return false;
        }
        $data = [
            "source" => [
                "identifier" => $phonenumber,
                "subtype"    => "WALLET",
            ],
            "payment_token" => $order->paytoken,
        ];

        $request = $this->http_post("acceptance/payments/pay", $data);

        if ($request) {
            if (isset($request->redirect_url)) {
                $return = new \stdClass;
                $return->redirecturl = $request->redirect_url;
                $return->iframeurl   = $request->iframe_redirection_url;
                $return->orderid     = $request->order->id;
                $return->method      = $request->source_data->type;
                return $return;
            }
        }

        debugging("Request wallet url failed. " . $request);
        return false;
    }
}
