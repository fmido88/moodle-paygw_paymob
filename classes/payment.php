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
 * Class payment
 *
 * @package    paygw_paymob
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment extends requester {
    /**
     * The cost in cents
     * @var int
     */
    protected $amountcents;
    /**
     * The billing data
     * @var array
     */
    protected $billing;
    /**
     * The order object
     * @var order
     */
    protected $order;
    /**
     * The gateway configuration
     * @var \stdClass
     */
    protected $config;
    /**
     * Description of the item.
     * @var string
     */
    protected $description;

    /**
     * Constructor.
     * @param string $component
     * @param string $paymentarea
     * @param string $itemid
     * @param string $description
     */
    public function __construct($component, $paymentarea, $itemid, $description = '') {
        $this->description = $description;

        $this->order = order::created_order($component, $paymentarea, $itemid);
        // Get all configuration preferences.
        $config = $this->order->get_gateway_config();

        $this->config = $config;
        $cents = 100;
        if ('omn' == $this->country ) {
            $cents = 1000;
        }

        $this->amountcents = round($this->order->get_cost(), 2) * $cents;

        $this->set_billing_data();
        parent::__construct($config->apikey, $config->public_key, $config->private_key);
    }

    /**
     * Set the billing data
     */
    protected function set_billing_data() {
        global $USER, $DB;

        $userphone = 'NA';
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

        $this->billing = [
            'email'        => $USER->email,
            'first_name'   => $USER->firstname,
            'last_name'    => $USER->lastname,
            'street'       => 'NA',
            'phone_number' => $userphone,
            'city'         => $USER->city ?? 'NA',
            'country'      => $USER->country ?? 'NA',
            'state'        => 'NA',
            'postal_code'  => 'NA',
        ];
    }

    /**
     * Get array of items.
     * @return array
     */
    protected function get_invoice_items() {
        $itemname = 'Order # ' . $this->order->get_id();
        $items = [];
        $items[] = [
            'name'     => $itemname,
            'amount'   => (int)((string)$this->amountcents),
            'quantity' => 1,
        ];

        return $items;
    }

    /**
     * Create intention
     * @param array $data
     * @return array
     */
    protected function create_intention($data) {

        $intention = $this->request('/v1/intention/', $data);

        $status = [
            'client_secret' => null,
            'success'       => false,
        ];

        if (isset($intention->detail)) {
            $status['message'] = $intention->detail;
            return $status;
        }

        if (isset($intention->amount)) {
            $status['message'] = $intention->amount[0];
            return $status;
        }

        if (isset($intention->billing_data)) {
            $status['message'] = 'Ops, there is missing billing information!';
            return $status;
        }

        if (isset($intention->integrations)) {
            $status['message'] = $intention->integrations[0];
            return $status;
        }

        if (isset($intention->client_secret)) {
            $status['success']       = true;
            $status['client_secret'] = $intention->client_secret;
            $status['pm_orderid']    = $intention->id;
            $status['amount_cent']   = $intention->intention_detail->amount;

            $this->order->set_pm_orderid($intention->id, false);
            $this->order->update_status('intended');
        } else {
            $status['message'] = $intention->code ?? json_decode($intention);
        }

        return $status;
    }

    /**
     * Create payment
     * @return array
     */
    public function create_payment() {
        global $USER;
        $price = (int) (string) $this->amountcents;

        $data  = [
            'amount'            => $price,
            'currency'          => $this->order->get_currency(),
            'notification_url'  => $this->callbackurl->out(),
            'redirection_url'   => $this->callbackurl->out(),
            'payment_methods'   => $this->get_integration_ids_array(),
            'billing_data'      => $this->billing,
            'items'             => $this->get_Invoice_Items(),
            'extras'            => [
                'local_order_id' => $this->order->get_id(),
                'paymentarea'    => $this->order->get_paymentarea(),
                'component'      => $this->order->get_component(),
                'itemid'         => $this->order->get_itemid(),
                'userid'         => $USER->id,
            ],
            'special_reference' => $this->order->get_id() . '_' . time(),
        ];

        return $this->create_Intention($data);
    }

    /**
     * Get the payment url
     * this return array
     * 'success' => bool
     * 'url' => \moodle_url
     * 'error' => string of error message
     * @return array
     */
    public function get_intention_url() {
        $cost = $this->order->get_raw_cost();
        if ($cost < $this->config->minimum_allowed ?? 0) {
            return [
                'success' => false,
                'error'   => get_string('low_payment', 'paygw_paymob', $this->config->minimum_allowed),
            ];
        }

        if (empty($this->config->legacy)) {
            $urlbase = $this->get_api_url();
            $request = $this->create_payment();
            $success  = $request['success'];
            if (!$success) {
                return [
                    'success' => false,
                    'error'   => $request['message'],
                ];
            }

            $params = [
                'publicKey'    => $this->publickey,
                'clientSecret' => trim($request['client_secret']),
            ];

            $url = new \moodle_url($urlbase . '/unifiedcheckout/', $params);
        } else {
            $params = [
                'component'   => $this->order->get_component(),
                'paymentarea' => $this->order->get_paymentarea(),
                'itemid'      => $this->order->get_itemid(),
                'description' => $this->description,
            ];
            $url = new \moodle_url('/payment/gateway/paymob/method.php', $params);
        }

        return [
            'url'     => $url->out(false),
            'success' => true,
        ];
    }
    /**
     * Get array of integration id from the configurations
     * @return array[int]
     */
    private function get_integration_ids_array() {
        $allitegrations = explode(',' , $this->config->integration_ids_hidden);

        $matchingids    = [];
        $integrationids = [];

        foreach ($allitegrations as $entry) {
            $parts = explode( ':', $entry );
            $id    = trim($parts[0]);
            if (isset($parts[2])) {
                $currency = trim(substr( $parts[2], strpos( $parts[2], '(' ) + 1, -2 ));
                if (in_array($id, $this->config->integration_ids) && $currency === $this->order->get_currency()) {
                    $matchingids[] = $id;
                }
            }
        }

        if (!empty($matchingids)) {
            foreach ($matchingids as $id) {
                $id = (int) $id;
                if ( $id > 0 ) {
                    array_push($integrationids, $id);
                }
            }
        }

        if (empty($integrationids) ) {
            foreach ($this->config->integration_ids as $id) {
                $id = (int)$id;
                if ($id > 0) {
                    array_push($integrationids, $id);
                }
            }
        }

        return $integrationids;
    }
}
