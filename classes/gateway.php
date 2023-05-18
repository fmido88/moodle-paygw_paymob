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
     * @return array<string>
     */
    public static function get_supported_currencies(): array {
        return ['EGP', 'USD', 'EUR', 'GBP'];
    }

    /**
     * Configuration form for the gateway instance
     *
     * Use $form->get_mform() to access the \MoodleQuickForm instance
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('text', 'apikey', get_string('apikey', 'paygw_paymob'));
        $mform->setType('apikey', PARAM_TEXT);
        $mform->addHelpButton('apikey', 'apikey', 'paygw_paymob');

        $mform->addElement('text', 'hmac_secret', get_string('hmac_secret', 'paygw_paymob'));
        $mform->setType('hmac_secret', PARAM_TEXT);
        $mform->addHelpButton('hmac_secret', 'hmac_secret', 'paygw_paymob');

        $mform->addElement('text', 'IntegrationIDcard', get_string('IntegrationIDcard', 'paygw_paymob'));
        $mform->setType('IntegrationIDcard', PARAM_INT);
        $mform->addHelpButton('IntegrationIDcard', 'IntegrationIDcard', 'paygw_paymob');

        $mform->addElement('text', 'iframe_id', get_string('iframe_id', 'paygw_paymob'));
        $mform->setType('iframe_id', PARAM_TEXT);
        $mform->addHelpButton('iframe_id', 'iframe_id', 'paygw_paymob');

        $mform->addElement('text', 'IntegrationIDwallet', get_string('IntegrationIDwallet', 'paygw_paymob'));
        $mform->setType('IntegrationIDwallet', PARAM_INT);
        $mform->addHelpButton('IntegrationIDwallet', 'IntegrationIDwallet', 'paygw_paymob');

        $mform->addElement('text', 'IntegrationIDkiosk', get_string('IntegrationIDkiosk', 'paygw_paymob'));
        $mform->setType('IntegrationIDkiosk', PARAM_INT);
        $mform->addHelpButton('IntegrationIDkiosk', 'IntegrationIDkiosk', 'paygw_paymob');

        $mform->addElement('text', 'discount', get_string('discount', 'paygw_paymob'));
        $mform->setType('discount', PARAM_INT);
        $mform->addHelpButton('discount', 'discount', 'paygw_paymob');

        $mform->addElement('text', 'discountcondition', get_string('discountcondition', 'paygw_paymob'));
        $mform->setType('discountcondition', PARAM_INT);
        $mform->addHelpButton('discountcondition', 'discountcondition', 'paygw_paymob');

        global $CFG;
        $mform->addElement('html', '<span class="lable-callback">'.get_string('callback', 'paygw_paymob').':</span><br>');
        $mform->addElement('html', '<span class="callback_url">'.$CFG->wwwroot.'/payment/gateway/paymob/callback.php</span><br>');
        $mform->addElement('html', '<span class="lable-callback">'.get_string('callback_help', 'paygw_paymob').'</span>');
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
        if ($data->enabled &&
                (empty($data->apikey)
                || (
                    (empty($data->IntegrationIDcard) || empty($data->iframe_id))
                    && empty($data->IntegrationIDwallet
                    && empty($data->IntegrationIDkiosk))
                    )
                )
            ) {
            $errors['enabled'] = get_string('gatewaycannotbeenabled', 'payment');
        }
    }
}
