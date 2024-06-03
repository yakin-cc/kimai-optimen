/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [KIMAI] KimaiAjaxModalForm
 *
 * allows to assign the given selector to any element, which then is used as click-handler:
 * opening a modal with the content from the URL given in the elements 'data-href' or 'href' attribute
 */

import jQuery from 'jquery';
import KimaiReducedClickHandler from "./KimaiReducedClickHandler";

export default class KimaiAjaxModalForm extends KimaiReducedClickHandler {

    constructor(selector) {
        super();
        this.selector = selector;
    }

    getId() {
        return 'modal';
    }

    init() {
        const self = this;
        this.isDirty = false;

        this.modal = jQuery('#remote_form_modal');
        this.modal
            .on('hide.bs.modal', function (e) {
                if (self.isDirty) {
                    if (jQuery('#remote_form_modal .modal-body .remote_modal_is_dirty_warning').length === 0) {
                        const msg = self.getContainer().getTranslation().get('modal.dirty');
                        jQuery('#remote_form_modal .modal-body').prepend('<p class="'+(self.modal.hasClass('modal-danger') ? 'well well-sm ' : '') + 'text-danger small remote_modal_is_dirty_warning">' + msg + '</p>');
                    }
                    e.preventDefault();
                    return;
                }
                jQuery(self._getFormIdentifier()).off('change', self._isDirtyHandler);
                self.isDirty = false;
                self.getContainer().getPlugin('event').trigger('modal-hide');
            })
            .on('hidden.bs.modal', function () {
                // kill all references, so GC can kick in
                self.getContainer().getPlugin('form').destroyForm(self._getFormIdentifier());
                jQuery('#remote_form_modal .modal-body').replaceWith('');
            })
            .on('show.bs.modal', function () {
                self.getContainer().getPlugin('event').trigger('modal-show');
            });

        this._addClickHandler(this.selector, function(href) {
            self.openUrlInModal(href);
        });
    }

    openUrlInModal(url, errorHandler) {
        const self = this;

        if (errorHandler === undefined) {
            errorHandler = function(xhr, err) {
                if (xhr.status === undefined || xhr.status !== 403) {
                    window.location = url;
                }
            };
        }

        jQuery.ajax({
            url: url,
            success: function(html) {
                self._openFormInModal(html);
            },
            error: errorHandler
        });
    }

    /**
     * Returns the CSS selector for the modal form.
     * 
     * @returns {string}
     * @private
     */
    _getFormIdentifier() {
        return '#remote_form_modal .modal-content form';
    }

    _openFormInModal(html) {
        const self = this;

        let formIdentifier = this._getFormIdentifier();
        // if any of these is found in a response, the form will be re-displayed
        let flashErrorIdentifier = 'div.alert-error';
        // messages to show above the form
        let flashMessageIdentifier = 'div.alert';
        let form = jQuery(formIdentifier);
        let remoteModal = this.modal;

        // will be (re-)activated later
        form.off('submit');

        // load new form from given content
        if (jQuery(html).find('#form_modal .modal-content').length > 0) {
            // Support changing modal importance/types
            remoteModal.on('hidden.bs.modal', function () {
                if (remoteModal.hasClass('modal-danger')) {
                    remoteModal.removeClass('modal-danger');
                }
            });

            if (jQuery(html).find('#form_modal').hasClass('modal-danger')) {
                remoteModal.addClass('modal-danger');
            }

            // Support changing modal sizes
            let modalDialog = remoteModal.find('.modal-dialog');
            let largeModal = jQuery(html).find('.modal-dialog').hasClass('modal-lg');
            if (largeModal && !modalDialog.hasClass('modal-lg')) {
                modalDialog.addClass('modal-lg');
            }
            if (!largeModal && modalDialog.hasClass('modal-lg')) {
                modalDialog.removeClass('modal-lg');
            }

            jQuery('#remote_form_modal .modal-content').replaceWith(
                jQuery(html).find('#form_modal .modal-content')
            );

            jQuery('#remote_form_modal [data-dismiss=modal]').on('click', function() {
                self.isDirty = false;
            });

            // activate new loaded widgets
            self.getContainer().getPlugin('form').activateForm(formIdentifier);
        }

        // show error flash messages
        let flashMessages = jQuery(html).find(flashMessageIdentifier);
        if (flashMessages.length > 0) {
            jQuery('#remote_form_modal .modal-body').prepend(flashMessages);
        }

        // -----------------------------------------------------------------------
        // a fix for firefox focus problems with datepicker in modal
        // see https://github.com/kevinpapst/kimai2/issues/618
        let enforceModalFocusFn = jQuery.fn.modal.Constructor.prototype.enforceFocus;
        jQuery.fn.modal.Constructor.prototype.enforceFocus = function() {};
        remoteModal.on('hidden.bs.modal', function () {
            jQuery.fn.modal.Constructor.prototype.enforceFocus = enforceModalFocusFn;
        });
        // -----------------------------------------------------------------------

        remoteModal.modal('show');

        // the new form that was loaded via ajax
        form = jQuery(formIdentifier);

        this._isDirtyHandler = function(e) {
            self.isDirty = true;
        }
        form.on('change', this._isDirtyHandler);

        // click handler for modal save button, to send forms via ajax
        form.on('submit', function(event) {
            // if the form has a target, we let the normal HTML flow happen
            if (form.attr('target') !== undefined) {
                return true;
            }

            // otherwise we do some AJAX magic to process the form in the background
            const btn = jQuery(formIdentifier + ' button[type=submit]').button('loading');
            const eventName = form.attr('data-form-event');
            const events = self.getContainer().getPlugin('event');
            const alert = self.getContainer().getPlugin('alert');

            event.preventDefault();
            event.stopPropagation();

            jQuery.ajax({
                url: form.attr('action'),
                type: form.attr('method'),
                data: form.serialize(),
                success: function(html) {
                    btn.button('reset');
                    let hasFieldError = jQuery(html).find('#form_modal .modal-content .has-error').length > 0;
                    let hasFormError = jQuery(html).find('#form_modal .modal-content ul.list-unstyled li.text-danger').length > 0;
                    let hasFlashError = jQuery(html).find(flashErrorIdentifier).length > 0;

                    if (hasFieldError || hasFormError || hasFlashError) {
                        self._openFormInModal(html);
                    } else {
                        events.trigger(eventName);

                        // try to find form defined messages first ...
                        let msg = form.attr('data-msg-success');
                        if (msg === null || msg === undefined) {
                            // ... but if none was available, check the response to find server rendered flash-message
                            let flashMessage = jQuery(html).find('section.content div.row div.alert.alert-success');
                            if (flashMessage.length > 0) {
                                let flashContent = flashMessage.contents();
                                if (flashContent.length === 3) {
                                    msg = flashContent[2].textContent;
                                }
                            }
                        }

                        // ... and if even that is not available, we use a generic fallback message
                        if (msg === null || msg === undefined) {
                            msg = 'action.update.success';
                        }
                        self.isDirty = false;
                        remoteModal.modal('hide');
                        alert.success(msg);
                    }
                    return false;
                },
                error: function(xhr, err) {
                    let message = form.attr('data-msg-error');
                    if (message === null || message === undefined) {
                        message = 'action.update.error';
                    }
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        err = xhr.responseJSON.message;
                    } else if (xhr.status && xhr.statusText) {
                        err = '[' + xhr.status +'] ' + xhr.statusText;
                    }
                    alert.error(message, err);
                    // this is useful for changing form fields and retrying to save (and in development to test form changes)
                    setTimeout(function() {
                        btn.button('reset');
                    }, 1500);
                }
            });
        });
    }

}
