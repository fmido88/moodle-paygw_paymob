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
 * Perform actions like refund, void or inquiry a transaction
 *
 * @module     paygw_paymob/actions
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
// eslint-disable-next-line camelcase
import {get_string} from 'core/str';
import Prefetch from 'core/prefetch';

Prefetch.prefetchStrings('paygw_paymob', [
    'voiding_status',
    'voided_successfully',
    'voided_failed',
    'refunding_status',
    'refunding_successfully',
    'refunding_failed',
    'transaction_inquiry'
]);

/**
 * Call ajax to retrieve the requested data
 * @param {Object} data
 * @param {String} purpose (void, refund or inquiry)
 * @returns {Promise<object>}
 */
function callAjax(data, purpose) {
    let request = Ajax.call([{
        methodname: 'paygw_paymob_' + purpose,
        args: data
    }]);
    return request[0];
}

/**
 * Void a transaction.
 * @param {*} id
 * @returns {Promise<void>}
 */
function voidTransaction(id) {
    let data = {id: id};
    return callAjax(data, 'void').done((data) => {
        let title = get_string('voiding_status', 'paygw_paymob');
        let message;
        if (data.status === 'success') {
            message = get_string('voided_successfully', 'paygw_paymob');
        } else if (data.msg) {
            message = data.msg;
        } else {
            message = get_string('voided_failed', 'paygw_paymob');
        }
        return Notification.alert(title, message, get_string('ok')).then((modal) => {
            return modal.show();
        });
    }).fail((error) => {
        throw error;
    });
}

/**
 * Refund certain transaction
 * If no amount passed the whole paid amount will be refunded
 * @param {Number} id
 * @param {Number} amount
 * @returns {Promise<void>}
 */
function refundTransaction(id, amount = 0) {
    let data = {
        id: id,
        amount: amount
    };
    return callAjax(data, 'refund').done((data) => {
        let title = get_string('refunding_status', 'paygw_paymob');
        let message;
        if (data.status === 'success') {
            message = get_string('refunding_successfully', 'paygw_paymob');
        } else if (data.msg) {
            message = data.msg;
        } else {
            message = get_string('refunding_failed', 'paygw_paymob');
        }
        return Notification.alert(title, message, get_string('ok')).then((modal) => {
            modal.show();
            return;
        });
    }).fail((error) => {
        throw error;
    });
}

/**
 * Get inquiry for certain transaction.
 * @param {Number} id
 * @returns {Promise<void>}
 */
function getInquiry(id) {
    let data = {id: id};
    return callAjax(data, 'inquiry').done(async(data) => {
        let message;
        if (data.error) {
            message = data.error;
        } else {
            message = await Templates.render('paygw_paymob/inquiry', data);
        }
        let title = get_string('transaction_inquiry', 'paygw_paymob');
        let modal = await Notification.alert(title, message, get_string('ok'));
        modal.show();
        return;
    }).fail((error) => {
        throw error;
    });
}

export const init = () => {
    const allButtons = $('button[data-action="void"], button[data-action="refund"], button[data-action="inquiry"]');

    $('button[data-action="void"]').on("click", function() {
        allButtons.prop('disabled', true);
        voidTransaction($(this).data('orderid')).always(() => {
            allButtons.prop('disabled', false);
            $(this).hide();
        });
    });

    $('button[data-action="refund"]').on("click", function() {
        allButtons.prop('disabled', true);
        refundTransaction($(this).data('orderid')).always(() => {
            allButtons.prop('disabled', false);
            $(this).hide();
        });
    });

    $('button[data-action="inquiry"]').on("click", function() {
        allButtons.prop('disabled', true);
        getInquiry($(this).data('orderid')).always(() => {
            allButtons.prop('disabled', false);
        });
    });
};
