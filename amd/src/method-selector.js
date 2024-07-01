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
 * TODO describe module method-selector
 *
 * @module     paygw_paymob/method-selector
 * @copyright  2024 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
let wwwroot;
/**
 * Display extra form fields according to the method.
 */
function extraData() {
    const phoneNumberInput = document.getElementById('phone-number');
    const phoneGroup = document.getElementById('phone-group');
    const paymentTypeSelect = document.getElementById('payment-type');
    const savedCardsGroup = document.getElementById('saved-cards-group');
    if (paymentTypeSelect.value === 'wallet') {
        if (phoneGroup) {
            phoneGroup.style.display = 'block';
        }
        if (savedCardsGroup) {
            savedCardsGroup.style.display = 'none';
        }
    } else if (paymentTypeSelect.value === 'card') {
        if (phoneGroup) {
            phoneGroup.style.display = 'none';
        }
        if (phoneNumberInput) {
            phoneNumberInput.value = '';
        }
        if (savedCardsGroup) {
            savedCardsGroup.style.display = 'block';
        }
    } else {
        if (phoneGroup) {
            phoneGroup.style.display = 'none';
        }
        if (phoneNumberInput) {
            phoneNumberInput.value = '';
        }
        if (savedCardsGroup) {
            savedCardsGroup.style.display = 'none';
        }
    }
}

/**
 * Change the image according to the selected method.
 */
function changeImage() {
    const paymentTypeSelect = document.getElementById('payment-type');
    // Get references to the necessary elements
    const imagePlaceholder = document.querySelector('.paymob-image-place-holder');
    // Define a mapping of payment method values to image URLs
    const imageUrls = {
        card: wwwroot + '/payment/gateway/paymob/img/visa_mastercard.png',
        aman: wwwroot + '/payment/gateway/paymob/img/aman.png',
        wallet: wwwroot + '/payment/gateway/paymob/img/Wallets.png'
    };
    const imageAlts = {
        card: 'visa_mastercard',
        aman: 'aman',
        wallet: 'Wallets'
    };
    // Get the selected payment method value
    const selectedValue = paymentTypeSelect.value;

    // Create an image element with the corresponding URL
    const imageElement = document.createElement('img');
    imageElement.src = imageUrls[selectedValue];
    imageElement.classList.add('paymob-payment-method-image'); // Add a class to the image element
    imageElement.alt = imageAlts[selectedValue];
    // Remove any existing images from the image placeholder
    imagePlaceholder.innerHTML = '';

    // Append the new image to the image placeholder
    imagePlaceholder.appendChild(imageElement);
}

export const init = (url) => {
    wwwroot = url;
    extraData();
    changeImage();
    $('#payment-type').on("change", function() {
        extraData();
        changeImage();
    });
};