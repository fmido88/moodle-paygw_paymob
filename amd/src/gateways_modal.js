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
 * This module is responsible for paymob content in the gateways modal.
 *
 * @module     paygw_paymob/gateway_modal
 * @copyright  2022 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import prefetch from 'core/prefetch';
// eslint-disable-next-line camelcase
import {get_string} from 'core/str';
import Ajax from 'core/ajax';

prefetch.prefetchStrings('paygw_paymob', ['error']);
/**
 * Return the payment url requested from paymob.
 *
 * @param {string} component
 * @param {string} paymentArea
 * @param {number} itemId
 * @param {string} description
 * @returns {Promise}
 */
function getUrl(component, paymentArea, itemId, description) {
    let requests = Ajax.call([{
        methodname: 'payge_paymob_get_payment_url',
        args: {
            component: component,
            paymentarea: paymentArea,
            itemid: itemId,
            description: description
        }
    }]);
    return requests[0];
}

export const process = async(component, paymentArea, itemId, description) => {
    return getUrl(component, paymentArea, itemId, description).then(async(ajaxdata) => {
        if (ajaxdata.success && ajaxdata.url) {
            window.location.href = ajaxdata.url;
            return new Promise(() => null);
        } else {
            let msg;
            if (ajaxdata.error) {
                msg = ajaxdata.error;
            } else {
                msg = await get_string('error', 'paygw_paymob', ajaxdata.error);
            }
            throw new Error(msg);
        }
    });
};
