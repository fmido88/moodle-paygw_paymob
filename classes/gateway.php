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
 * Contains class for Paymob payment gateway.
 *
 * @package    paygw_paymob
 * @copyright  2023 Mo. Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_paymob;

/**
 * The gateway class for PayPal payment gateway.
 *
 * @package    paygw_paymob
 * @copyright  2023 Mo. Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {
    /**
     * Returns the list of currencies that the payment gateway supports.
     * return an array of the currency codes in the three-character ISO-4217 format
     *
     * as paymob work on different countries, so different currencies are supported
     * we return only those available for certain enabled integrations.
     *
     * @return array<string>
     */
    public static function get_supported_currencies(): array {
        global $PAGE;
        if (!empty($PAGE->context) && $PAGE->context instanceof \context) {
            $context = $PAGE->context;
        } else {
            $context = \context_system::instance();
        }

        $currencies = [];
        $accounts = \core_payment\helper::get_payment_accounts_menu($context);
        $accounts = array_keys($accounts);
        foreach ($accounts as $id) {
            $account = new \core_payment\account($id);
            if ($account && $account->get('enabled')) {
                $gateway = $account->get_gateways()['paymob'] ?? null;
            }
            if (empty($gateway)) {
                continue;
            }
            $config = $gateway->get_configuration();
            $currencies = array_merge($currencies, self::get_available_currencies($config));
        }

        return array_unique($currencies);
    }

    /**
     * Configuration form for the gateway instance
     *
     * Use $form->get_mform() to access the \MoodleQuickForm instance
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        global $OUTPUT, $CFG, $PAGE;

        $mform = $form->get_mform();

        $mform->addElement('html', $OUTPUT->render_from_template('paygw_paymob/admin', ['wwwroot' => $CFG->wwwroot]));

        $mform->addElement('checkbox', 'legacy', get_string('legacy', 'paygw_paymob'), get_string('legacy', 'paygw_paymob'));
        $mform->addHelpButton('legacy', 'legacy', 'paygw_paymob');

        $mform->addElement('text', 'apikey', get_string('apikey', 'paygw_paymob'));
        $mform->setType('apikey', PARAM_TEXT);
        $mform->addHelpButton('apikey', 'apikey', 'paygw_paymob');

        $mform->addElement('passwordunmask', 'hmac', get_string('hmac', 'paygw_paymob'));
        $mform->setType('hmac', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('hmac', 'hmac', 'paygw_paymob');

        $mform->addElement('passwordunmask', 'public_key', get_string('public_key', 'paygw_paymob'));
        $mform->setType('public_key', PARAM_ALPHANUMEXT);
        $mform->hideIf('public_key', 'legacy', 'checked');

        $mform->addElement('passwordunmask', 'private_key', get_string('private_key', 'paygw_paymob'));
        $mform->setType('private_key', PARAM_ALPHANUMEXT);
        $mform->hideIf('private_key', 'legacy', 'checked');

        $mform->addElement('hidden', 'integration_ids_hidden');
        $mform->setType('integration_ids_hidden', PARAM_RAW_TRIMMED);

        $mform->addElement('hidden', 'hmac_hidden');
        $mform->setType('hmac_hidden', PARAM_ALPHANUMEXT);

        $options = [];
        $select = $mform->addElement('select', 'integration_ids_select', get_string('integration_ids', 'paygw_paymob'), $options);
        $select->setMultiple(true);

        $mform->addElement('hidden', 'integration_ids');
        $mform->setType('integration_ids', PARAM_RAW_TRIMMED);

        $mform->addElement('text', 'iframe_id', get_string('iframe_id', 'paygw_paymob'));
        $mform->setType('iframe_id', PARAM_TEXT);
        $mform->addHelpButton('iframe_id', 'iframe_id', 'paygw_paymob');
        $mform->hideIf('iframe_id', 'legacy');

        $mform->addElement('text', 'minimum_allowed', get_string('minimum_allowed', 'paygw_paymob'));
        $mform->setType('minimum_allowed', PARAM_FLOAT);
        $mform->addHelpButton('minimum_allowed', 'minimum_allowed', 'paygw_paymob');

        $callback = $OUTPUT->render_from_template('paygw_paymob/admin-callback', ['wwwroot' => $CFG->wwwroot]);
        $mform->addElement('static', 'callback', get_string('callback', 'paygw_paymob'), $callback);

        $mform->addElement('html', $OUTPUT->notification(get_string('legacy_warning', 'paygw_paymob'), 'warning', false));

        self::add_js($mform);
    }
    /**
     * Get integration options.
     * @param \MoodleQuickForm $mform
     * @return array
     */
    private static function get_integration_options($mform) {
        $id = optional_param('id', null, PARAM_INT);
        if (!$id) {
            return [];
        }

        $account = new \core_payment\account($id);
        $gateway = $account->get_gateways(false)['paymob'] ?? null;
        if (!$gateway) {
            return [];
        }

        $config = (object)$gateway->get_configuration();
        $all = $config->integration_ids_hidden ?? '';
        if (empty($all)) {
            $all = $mform->exportValue('integration_ids_hidden') ?? '';
        }
        return \paygw_paymob\utils::get_integration_ids_from_string($all);
    }
    /**
     * Add js to the admin form.
     * @param \MoodleQuickForm $mform
     */
    private static function add_js($mform) {
        global $PAGE;

        $data = self::get_js_params($mform);

        $PAGE->requires->js_call_amd('paygw_paymob/admin_form', 'init', ['data' => json_encode($data)]);
    }

    /**
     * Get currencies from available integrations.
     * @param array|\stdClass $config the payment gateway configuration
     * @return array[string]
     */
    private static function get_available_currencies($config) {
        $currencies = [];
        $config = (object)$config;
        $all = $config->integration_ids_hidden ?? '';
        if (empty($all)) {
            return [];
        }
        $methods = \paygw_paymob\utils::get_integration_ids_from_string($all, true);
        foreach ($methods as $method) {
            $currencies[] = $method->currency;
        }
        return array_unique($currencies);
    }
    /**
     * get the args to pass to js.
     * @param \MoodleQuickForm $mform
     */
    private static function get_js_params($mform) {
        $values = (object)$mform->exportValues();
        $data = (object)[
            'integration_id'     => $values->integration_ids ?? '',
            'integration_hidden' => $values->integration_ids_hidden ?? '',
            'hmac_hidden'        => $values->hmac_hidden ?? '',
        ];

        $id = optional_param('id', null, PARAM_INT);
        if (!$id) {
            return $data;
        }

        $account = new \core_payment\account($id);
        $gateway = $account->get_gateways(false)['paymob'] ?? null;
        if (!$gateway) {
            return $data;
        }

        $config = (object)$gateway->get_configuration();
        $data->integration_id = $config->integration_ids ?? $data->integration_ids;
        $data->integration_hidden = $config->integration_ids_hidden ?? $data->integration_hidden;
        $data->hmac_hidden = $config->hmac_hidden ?? $data->hmac_hidden;

        if (empty($data->integration_id)) {
            $data->integration_id = [];
        } else {
            $data->integration_id = json_decode($data->integration_id);
        }

        return $data;
    }

    /**
     * Validates the gateway configuration form.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors form errors (passed by reference)
     */
    public static function validate_gateway_form(\core_payment\form\account_gateway $form,
                                                 \stdClass $data, array $files, array &$errors): void {
        $ok = true;

        if ($data->enabled) {
            $legacy = !empty($data->legacy);

            if (empty($data->apikey)) {
                $errors['apikey'] = get_string('required');
                $ok = false;
            }

            if (empty($data->hmac_hidden)) {
                $errors['hmac'] = get_string('required');
                $ok = false;
            }

            if (empty($data->public_key) && !$legacy) {
                $errors['public_key'] = get_string('required');
                $ok = false;
            } else if (!$legacy && !utils::verify_key($data->public_key, 'public')) {
                $errors['public_key'] = get_string('invalid_key', 'paygw_paymob');
                $ok = false;
            }

            if (empty($data->private_key) && !$legacy) {
                $errors['private_key'] = get_string('required');
                $ok = false;
            } else if (!$legacy && !utils::verify_key($data->private_key, 'private')) {
                $errors['private_key'] = get_string('invalid_key', 'paygw_paymob');
                $ok = false;
            }

            if (!$ok) {
                $errors['enabled'] = get_string('gatewaycannotbeenabled', 'payment');
                return;
            }

            if (!$legacy && !utils::match_countries($data->private_key, $data->public_key)) {
                $errors['public_key'] =
                $errors['private_key'] = get_string('not_same_country', 'paygw_paymob');
                $ok = false;
            }

            if (!$legacy && !utils::match_mode($data->public_key, $data->private_key)) {
                $errors['public_key'] =
                $errors['private_key'] = get_string('not_same_mode', 'paygw_paymob');
                $ok = false;
            }

            if (empty($data->integration_ids)) {
                $errors['integration_ids_select'] = get_string('atleast_one_integration', 'paygw_paymob');
                $ok = false;
            } else {
                $integrationids = json_decode($data->integration_ids);
                $all = utils::get_integration_ids_from_string($data->integration_ids_hidden, true);
                $types = [];
                foreach ($integrationids as $id) {
                    $types[] = $all[$id]->type;
                }
                if (count($types) > count(array_unique($types))) {
                    $errors['integration_ids_select'] = get_string('no_same_type_integrations', 'paygw_paymob');
                    $ok = false;
                }

            }

            if (!$ok) {
                $errors['enabled'] = get_string('gatewaycannotbeenabled', 'payment');
            }
        }
    }
}
