/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [KIMAI] KimaiDateTimePicker: activate the (datetime picker) field in timesheet edit dialog
 */

import jQuery from 'jquery';
import KimaiPlugin from '../KimaiPlugin';

export default class KimaiDateTimePicker extends KimaiPlugin {

    constructor(selector) {
        super();
        this.selector = selector;
    }

    getId() {
        return 'date-time-picker';
    }

    activateDateTimePicker(selector) {
        const TRANSLATE = this.getContainer().getTranslation();
        const DATE_UTILS = this.getContainer().getPlugin('date');
        const firstDow = this.getConfiguration('first_dow_iso') % 7;
        const is24hours = this.getConfiguration('twentyFourHours');

        jQuery(selector + ' ' + this.selector).each(function(index) {
            let localeFormat = jQuery(this).data('format');
            jQuery(this).daterangepicker({
                singleDatePicker: true,
                timePicker: true,
                timePicker24Hour: is24hours,
                showDropdowns: true,
                autoUpdateInput: false,
                drops: 'down',
                locale: {
                    format: localeFormat,
                    firstDay: firstDow,
                    applyLabel: TRANSLATE.get('confirm'),
                    cancelLabel: TRANSLATE.get('cancel'),
                    customRangeLabel: TRANSLATE.get('customRange'),
                    daysOfWeek: DATE_UTILS.getWeekDaysShort(),
                    monthNames: DATE_UTILS.getMonthNames(),
                }
            });

            jQuery(this).on('show.daterangepicker', function (ev, picker) {
                if (picker.element.offset().top - jQuery(window).scrollTop() + picker.container.outerHeight() + 30 > jQuery(window).height()) {
                    // "up" is not possible here, because the code is triggered on many mobile phones and the picker then appears out of window
                    picker.drops = 'auto';
                    picker.move();
                }
            });

            jQuery(this).on('apply.daterangepicker', function(ev, picker) {
                jQuery(this).val(picker.startDate.format(localeFormat));
                jQuery(this).trigger("change");
            });
        });
    }

    destroyDateTimePicker(selector) {
        jQuery(selector + ' ' + this.selector).each(function(index) {
            if (jQuery(this).data('daterangepicker') !== undefined) {
                jQuery(this).data('daterangepicker').remove();
            }
        });
    }

}
