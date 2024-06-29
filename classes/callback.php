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
 * Class callback
 *
 * @package    paygw_paymob
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class callback {
    /**
     * Handle all callbacks from paymob.
     * This function will make sure of security checks first and all cleanups then it will process the
     * order and redirection.
     * @return void
     */
    public function process() {
        if (security::filter_var('REQUEST_METHOD', 'SERVER') === 'POST') {
            $this->call_webhook_action();
        } else if (security::filter_var('REQUEST_METHOD', 'SERVER') === 'GET') {
            $this->call_return_action();
        } else {
            die("invalid request " . (security::filter_var('REQUEST_METHOD', 'SERVER')));
        }
    }

    /**
     * Callback for webhook actions (POST)
     */
    protected function call_webhook_action() {
        $postdata = file_get_contents('php://input');
        $jsondata = json_decode($postdata, true);

        if (isset($jsondata['type']) && security::filter_var('hmac', 'REQUEST') && 'TRANSACTION' === $jsondata['type'] ) {
            $this->accept_webhook($jsondata);
        } else {
            $this->flash_webhook($jsondata);
        }
    }

    /**
     * Callback for webhook actions of type accept
     * @param array $jsondata
     */
    protected function accept_webhook($jsondata) {
        $cleaned = security::clean_param('', $jsondata);

        $obj  = $cleaned['obj'];
        $type = $cleaned['type'];
        if (!empty($obj['order'])) {
            $pmorderid = $obj['order']['id'];
        } else if (!empty($obj['order_id'])) {
            $pmorderid = $obj['order_id'];
        }
        if (isset($pmorderid)) {
            $order = order::instance_form_pm_orderid($pmorderid);
        } else {
            $orderid = substr($obj['order']['merchant_order_id'], 0, -11);

            $order = new order($orderid);
        }

        $config = $order->get_gateway_config();
        $legacy = (bool)($config->legacy ?? false);
        if ($legacy) {
            global $CFG;
            include_once("$CFG->dirroot/payment/gateway/paymob/callback_leg.php");
            return;
        }

        $hmackey = $config->hmac_hidden;
        if (security::verify_hmac($hmackey, $jsondata, null, security::filter_var('hmac', 'REQUEST'))) {

            $order->verify_order_changeable();

            $integrationid = $obj['integration_id'];
            $type          = $obj['source_data']['type'];
            $subtype       = $obj['source_data']['sub_type'];
            $transaction   = $obj['id'];
            $paymobid      = $obj['order']['id'];

            $order->add_order_note_data($integrationid, $type, $subtype, $transaction, $paymobid);

            $status = utils::get_order_status($obj);
            if ($status === 'success') {
                $order->payment_complete();
            } else {
                $order->update_status($status);
            }
            notifications::notify($order, $status);

            die("Order updated: $orderid");
        } else {
            die("can not verify order: $orderid");
        }
    }

    /**
     * Callback for webhook action of type flash.
     * @param array $jsondata
     */
    protected function flash_webhook($jsondata) {
        $cleaned = security::clean_param('', $jsondata);
        $orderid = $cleaned['intention']['extras']['creation_extras']['local_order_id'];

        $order = new order($orderid);

        $orderintensionid = $order->get_pm_orderid();
        $orderamount      = $order->get_amount_cents();

        if ($orderintensionid != $cleaned['intention']['id']) {
            die("intention ID is not matched for order: $orderid");
        }

        if ($orderamount != $cleaned['intention']['intention_detail']['amount']) {
            die("intension amount are not matched for order : $orderid");
        }

        $country = utils::get_country_code($order->get_gateway_config()->public_key);
        $cents = 100;
        if ('omn' == $country) {
            $cents = 1000;
        }

        if (!security::verify_hmac($order->get_gateway_config()->hmac_hidden, $jsondata,
                    [
                        'id'     => $orderintensionid,
                        'amount' => $orderamount,
                        'cents'  => $cents,
                    ]
                )
        ) {
            die("can not verify order: $orderid");
        }

        $order->verify_order_changeable();

        if (!empty($cleaned['transaction'])) {
            $trans         = $cleaned['transaction'];
            $transaction   = $trans['id'];
            $integrationid = $trans['integration_id'];
            $type          = $trans['source_data']['type'];
            $subtype       = $trans['source_data']['sub_type'];
            $paymobid      = $trans['order']['id'];

            $status = utils::get_order_status($trans);
            $order->add_order_note_data($integrationid, $type, $subtype, $transaction, $paymobid);
            if ($status === 'success') {
                $order->payment_complete();
            } else {
                $order->update_status($status);
            }

            die("Order updated: $orderid");
        }
        die("Invalid response");
    }

    /**
     * Redirection action.
     */
    protected function call_return_action() {
        $orderid = utils::extract_order_id(security::filter_var('merchant_order_id'));
        $paymoborder = security::filter_var('order');
        if (!empty($orderid)) {
            $order = new order($orderid);
        } else {
            $order = order::instance_form_pm_orderid($paymoborder);
        }

        $config = $order->get_gateway_config();
        $legacy = (bool)($config->legacy ?? false);
        if ($legacy) {
            global $CFG;
            include_once("$CFG->dirroot/payment/gateway/paymob/callback_leg.php");
            return;
        }

        if (!security::verify_hmac($config->hmac_hidden, $_GET)) {
            redirect(new \moodle_url('/'), get_string('verification_failed', 'paygw_paymob'), null, 'error');
            exit();
        }

        $integrationid = security::filter_var('integration_id');
        $type          = security::filter_var('source_data_type');
        $subtype       = security::filter_var('source_data_sub_type' );
        $id            = security::filter_var('id');

        $url = $order->get_redirect_url();

        $obj = security::clean_param('', $_GET);
        $status = utils::get_order_status($obj);
        if ($status === 'success') {
            if (!$order->verify_order_changeable(false)) {
                redirect($url, get_string('payment_processing', 'paygw_paymob'), null, 'success');
                exit();
            }

            $order->add_order_note_data($integrationid, $type, $subtype, $id, $paymoborder, security::filter_var('data_message'));
            $order->update_status('processing');

            $msg = get_string('paymentresponse', 'paygw_paymob', $order->get_status());
            $type = \core\notification::SUCCESS;
            redirect($url, $msg, null, $type);
        } else {
            $gatewayerror = security::filter_var('data_message');
            $order->update_status('failed');
            $order->add_order_note_data($integrationid, $type, $subtype, $id, $paymoborder, $gatewayerror);
            $msg = get_string('paymentcancelled', 'paygw_paymob', $gatewayerror);
            $type = \core\notification::ERROR;
            redirect($url, $msg, null, $type);
        }

        exit();
    }
}
