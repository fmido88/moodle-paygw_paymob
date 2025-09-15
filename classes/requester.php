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
class requester {
    /**
     * The API key.
     * @var string
     */
    protected $apikey;
    /**
     * The authtoken from the request.
     * @var string
     */
    protected $authtoken;

    /**
     * The public key from paymob
     * dashboard.
     * @var string
     */
    protected $publickey;
    /**
     * The private key from paymob
     * dashboard.
     * @var string
     */
    protected $privatekey;
    /**
     * The base of api url endpoint.
     * @var string
     */
    protected $apiurl;
    /**
     * Country code.
     * @var string
     */
    protected $country;
    /**
     * The callback url.
     * @var \moodle_url
     */
    protected $callbackurl;
    /**
     * Reference for endpoints actions.
     *  'refund'         => Refund transaction POST
     * 'void'            => Voiding a transaction POST
     * 'auth'            => Get auth tokens POST
     * 'inquiry_transid' => Inquiry transaction by transaction id  GET
     * 'inquiry_orderid' => Inquiry transaction by order id
     * 'auth-capture'    => auth-capture transaction
     * 'intention'       => Intention
     * 'pay-token'       => Pay with saved token,
     * 'payment-links'   => create payment link,
     */
    protected const ACTIONS = [
        'refund'          => '/api/acceptance/void_refund/refund',
        'void'            => '/api/acceptance/void_refund/void',
        'auth'            => '/api/auth/tokens',
        'inquiry_transid' => '/api/acceptance/transactions/{transaction_id}',
        'inquiry_orderid' => '/api/ecommerce/orders/transaction_inquiry',
        'auth-capture'    => '/api/acceptance/capture',
        'intention'       => '/v1/intention/',
        'pay-token'       => '/api/acceptance/payments/pay',
        'payment-links'   => '/api/ecommerce/payment-links',
    ];
    /**
     * Constructor.
     * @param string $apikey
     * @param string $publickey
     * @param string $privatekey
     */
    public function __construct($apikey, $publickey, $privatekey) {
        $this->apikey = $apikey;
        $this->publickey = $publickey;
        $this->privatekey = $privatekey;

        if (!utils::match_countries($privatekey, $publickey)) {
            if (AJAX_SCRIPT) {
                throw new \moodle_exception('not_same_country', 'paygw_paymob');
            } else {
                debugging('The countries in the provided keys aren\'t the same.');
            }
        }

        $this->country = utils::get_country_code($this->privatekey);

        $this->callbackurl = new \moodle_url("/payment/gateway/paymob/callback.php");
    }

    /**
     * Get the base of endpoint url
     */
    protected function get_api_url() {

        if (isset($this->apiurl)) {
            return $this->apiurl;
        }

        $this->apiurl = utils::get_api_url($this->privatekey, true);
        return $this->apiurl;
    }

    /**
     * Function to perform requests to paymob endpoint.
     * @param string $action
     * @param array $data
     * @param string $method post or get
     * @param string $authheader override the auth header
     * @return object|string object of returned data or string on error.
     */
    protected function request($action, $data = [], $method = "post", $authheader = null) {
        global $CFG;
        require_once($CFG->libdir."/filelib.php");
        $method = strtolower($method);

        $curl = new \curl();

        if (array_key_exists($action, self::ACTIONS)) {
            $action = self::ACTIONS[$action];
        }

        $url = $this->get_api_url() . $action;
        $options = [
            'url'            => $url,
            'returntransfer' => true,
            'failonerror'    => false,
            'useragent'      => core_useragent::get_user_agent_string(),
        ];

        $options['httpheader'] = ['Content-Type: application/json'];

        if ($authheader) {
            $options['httpheader'][] = $authheader;
        } else if ($method == 'post') {
            $options['httpheader'][] = 'Authorization: Token ' . $this->privatekey;
        } else {
            $options['httpheader'][] = 'Authorization: Bearer ' . $this->get_auth_token();
        }

        $curl->setopt($options);
        $curl->setHeader($options['httpheader']);

        if ($method == 'post') {
            $response = $curl->post($url, json_encode($data), $options);
        } else {
            $response = $curl->get($url, $data);
        }

        $httpcode = $curl->get_info()['http_code'] ?? 0;
        $responsedata = json_decode($response, false);

        $curl->cleanopt();
        if ($httpcode != 200 && $httpcode != 201 ) {
            // Endpoint returned an error.
            self::debug($response, "endpoint return error ");
            return $responsedata;
        }

        return $responsedata;
    }

    /**
     * First step of paymob API workflow is to request auth token.
     * @return string|false the token or false on error.
     */
    private function request_token() {
        $data = [
            'api_key' => $this->apikey,
        ];

        $request = $this->request('/api/auth/tokens', $data);

        if ($request && isset($request->token)) {
            return $request->token;
        }

        self::debug($request, "Requesting token failed");
        return false;
    }

    /**
     * Get the auth token
     * @return string
     */
    protected function get_auth_token() {
        if (!isset($this->authtoken)) {
            $this->authtoken = $this->request_token();
        }
        return $this->authtoken;
    }

    /**
     * Get the hmac secret key
     * @return string|null
     */
    public function get_hmac() {
        $request = $this->request('/api/auth/hmac_secret/get_hmac', [], 'get');
        if (isset($request->hmac_secret)) {
            return $request->hmac_secret;
        }
        self::debug($request, 'error while trying to get the hmac');
        return null;
    }

    /**
     * Get array of all available integration ids.
     * @return array|null
     */
    public function get_integration_ids() {
        if (!utils::match_mode($this->publickey, $this->privatekey)) {
            if (AJAX_SCRIPT) {
                throw new \moodle_exception('not_same_mode', 'paygw_paymob');
            } else {
                debugging('public and private keys are not in the same mode');
                return null;
            }
        }

        $islive = utils::is_live($this->privatekey);
        $nomode = (int)$islive < 0;

        $data = [
            'is_plugin'     => 'true',
            'is_next'       => 'true',
            'page_size'     => 500,
            'is_deprecated' => 'false',
            'is_standalone' => 'false',
        ];
        $request = $this->request('/api/ecommerce/integrations', $data, 'get');

        if (!empty($request->results)) {

            $integrationids = [];
            foreach ($request->results as $key => $integration) {
                if (empty($integration->id)) {
                    continue;
                }

                $type = $integration->gateway_type;
                if ('VPC' == $type) {
                    $type = 'Card';
                } else if ('CAGG' == $type) {
                    $type = 'Aman';
                } else if ('UIG' == $type) {
                    $type = 'Wallet';
                }

                $online = $integration->integration_type == 'online_new';
                $online = $online || ($integration->integration_type == 'online');

                if ($online && !$integration->is_standalone
                    && ($integration->is_live == $islive || $nomode)) {

                    $integrationids[$integration->id] = [
                        'id'       => $integration->id,
                        'type'     => $type,
                        'name'     => $integration->integration_name,
                        'currency' => $integration->currency,
                        'live'     => $integration->is_live,
                    ];
                }
            }

            if (empty($integrationids)) {
                self::debug('There is no available valid integrations');
            }

            return $integrationids;
        }

        self::debug($request, 'Error while requesting integration ids');
        return null;
    }
    /**
     * Display debug message.
     *
     * @param string|object $request
     * @param string $msg
     * @throws \moodle_exception
     * @return void
     */
    protected static function debug($request, $msg = '') {
        $responseerror = '';
        if (is_string($request)) {
            $responseerror .= $request;
        } else {
            foreach ($request as $key => $value) {
                $responseerror .= $key . ': ' . $value . "\n";
            }
        }

        if (AJAX_SCRIPT) {
            throw new \moodle_exception("error in response: \n".$responseerror);
        }
        debugging($msg . ' ' . $responseerror, DEBUG_DEVELOPER);
    }
    /**
     * Log the data for the cases of debugging
     * @param mixed $data
     * @return void
     */
    public static function log($data) {
        // Using this for developing only.
        // Erase any contents in live versions.
    }
}
