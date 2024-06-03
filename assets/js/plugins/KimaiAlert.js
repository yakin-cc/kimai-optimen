/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [KIMAI] KimaiAlert: notifications for Kimai
 */

import Swal from 'sweetalert2'
import KimaiPlugin from "../KimaiPlugin";

export default class KimaiAlert extends KimaiPlugin {

    getId() {
        return 'alert';
    }

    /**
     * @param {string} title
     * @param {string|array} message
     */
    error(title, message) {
        const translation = this.getContainer().getTranslation();
        if (translation.has(title)) {
            title = translation.get(title);
        }
        if (translation.has(message)) {
            message = translation.get(message);
        }

        if (Array.isArray(message)) {
            Swal.fire({
                icon: 'error',
                title: title.replace('%reason%', ''),
                html: message.join('<br>'),
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: title.replace('%reason%', ''),
                text: message,
            });
        }
    }

    warning(message) {
        this._show('warning', message);
    }

    success(message) {
        this._toast('success', message);
    }

    info(message) {
        this._show('info', message);
    }

    _show(type, message) {
        const translation = this.getContainer().getTranslation();

        if (translation.has(message)) {
            message = translation.get(message);
        }

        Swal.fire({
            icon: type,
            title: message,
        });
    }

    _toast(type, message) {
        const translation = this.getContainer().getTranslation();

        if (translation.has(message)) {
            message = translation.get(message);
        }

        Swal.fire({
            timer: 2000,
            timerProgressBar: true,
            toast: true,
            position: 'top',
            showConfirmButton: false,
            icon: type,
            title: message,
        });
    }

    /**
     * Callback receives a value and needs to decide what should happen with it
     *
     * @param message
     * @param callback
     */
    question(message, callback) {
        const translation = this.getContainer().getTranslation();

        if (translation.has(message)) {
            message = translation.get(message);
        }

        Swal.fire({
            title: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: translation.get('confirm'),
            cancelButtonText: translation.get('cancel')
        }).then((result) => {
            callback(result.value);
        });
    }

}
