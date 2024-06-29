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
 * TODO describe module admin_form
 *
 * @module     paygw_paymob/admin_form
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Ajax from 'core/ajax';

let ajax_object = new Object();
/**
 * Call ajax request to retrieve required data.
 */
function callAjax() {
    let publickey = $('#id_public_key').val();
    let privatekey = $('#id_private_key').val();
    let legacy = $('input[name="legacy"]').is(':checked');
    if (legacy) {
        publickey = 'egy';
        privatekey = 'egy';
    }

    if ($('#id_apikey').val().length === 0
        || publickey.length === 0
        || privatekey.length === 0) {
        alert('Please provide Paymob API, public and secret keys');
    } else if (!legacy && (publickey.length < 20 || privatekey.length < 20)) {
        alert('Please provide correct public and secret keys or choose legacy mode');
    } else {
        $(".paygw-paymob-loader").css('display', 'block');
        let post = Ajax.call([{
                methodname: "paygw_paymob_get_admin_options",
                args: {
                    apikey: $('#id_apikey').val(),
                    publickey: publickey,
                    privatekey: privatekey,
                    accountid: $('input[name="accountid"]').val()
                }
            }]);

        // console.log(post);
        post[0].done(function (data) {
            // console.log(data);

            $('input[name="hmac"]').val(data.hmac);
            $('input[name="hmac_hidden"]').val(data.hmac);
            var html = '';
            var ids  = '';
            var allids = [];
            let integrationIDs = JSON.parse(data.integration_ids);
            $.each(integrationIDs, function (i, integration) {
                allids.push(integration.id);
                var text = integration.id + " : " + integration.name
                         + " (" + integration.type + " : " + integration.currency + " )";
                ids = ids + text + ',';
                if (ajax_object.integration_id.length > 0) {
                    var selected = '';
                    $.each(
                        ajax_object.integration_id,
                        function (ii, id) {
                            if (integration.id === id || parseInt( integration.id ) === parseInt( id )) {
                                selected = 'selected';
                            }
                        }
                    );
                }

                if (text !== '') {
                    html = html + "<option " + selected + " value=" + integration.id + ">" + text + "</option>";
                }
            });

            $('input[name="integration_ids_hidden"]').val(ids);

            if (html) {
                $('#id_integration_ids_select').html(html);
            }

            $('input[name="integration_ids"]').val(JSON.stringify(allids));

            $(".paygw-paymob-loader").fadeOut(10);
            $(".paygw-paymob-success_load").css('display', 'block');
            $(".paygw-paymob-success_load").fadeOut(500);

            $('#paygw-paymob-not-valid').css('display', 'none');
            $('#paygw-paymob-valid').css('display', 'inline-block');
        }).fail(function(error) {
                // console.log(error);
                $(".paygw-paymob-loader").fadeOut(10);
                alert(error.message);
                $(".paygw-paymob-failed_load").css('display', 'block');
                $(".paygw-paymob-failed_load").fadeOut(500);
                $('#paygw-paymob-not-valid').css('display', 'inline-block');
                $('#paygw-paymob-valid').css('display', 'none');
            }
        );

    }
}

export const init = ($data) => {
    ajax_object = JSON.parse($data);

    // console.log(ajax_object);
    $('#cpicon').on("click", function () {
            var copyText = document.getElementById('cburl').innerText;
            if (navigator && navigator.clipboard) {
                navigator.clipboard.writeText(copyText);
                alert('Copied!');
            } else {
                prompt("Copy link, then click OK.", copyText);
            }
        });

    $('#accept-login').on("click", function () {
        callAjax();
    });

    $(".paygw-paymob-loader").fadeOut(1500, function () {
        $('#id_integration_ids_select').html('');
        var integration_hidden = ajax_object.integration_hidden.split(",");
        $('#id_hmac').val(ajax_object.hmac_hidden);

        if (ajax_object.integration_hidden.length > 0) {
            $.each(integration_hidden, function (av_i, av_id) {
                var selected = '';
                if (av_id !== '') {
                    var integrationId = av_id.split( " :" );
                    $.each(ajax_object.integration_id, function (i, id) {
                        if (integrationId === id || parseInt(integrationId) === parseInt( id )) {
                            selected = 'selected';
                        }
                    });
                }

                $('#id_integration_ids_select').append( "<option " + selected + " value=" + av_id + ">" + av_id + "</option>" );
            });
        }
    });
    $('#id_integration_ids_select').on('change', function() {
        var selectedOptions = $(this).find('option:selected');

        var values = $.map(selectedOptions, function(option) {
            return $(option).val();
        });

        var valuesString = JSON.stringify(values);

        // Set the value of the input field
        $('input[name="integration_ids"]').val(valuesString);
    });
};
