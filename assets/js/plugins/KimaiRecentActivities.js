/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*!
 * [KIMAI] KimaiRecentActivities: responsible to reload the users recent activities
 */

import KimaiPlugin from '../KimaiPlugin';

export default class KimaiRecentActivities extends KimaiPlugin {

    constructor(selector) {
        super();
        this.selector = selector;
    }

    getId() {
        return 'recent-activities';
    }

    init() {
        const menu = document.querySelector(this.selector);
        // the menu can be hidden if user has no permissions to see it
        if (menu === null) {
            return;
        }

        const dropdown = menu.querySelector('ul.dropdown-menu');

        this.attributes = dropdown.dataset;
        this.itemList = dropdown.querySelector('li > ul.menu');

        const self = this;
        const handle = function() { self.reloadRecentActivities(); };

        // don't block initial browser rendering
        setTimeout(handle, 500);

        document.addEventListener('kimai.recentActivities', handle);
        document.addEventListener('kimai.timesheetUpdate', handle);
        document.addEventListener('kimai.timesheetDelete', handle);
        document.addEventListener('kimai.activityUpdate', handle);
        document.addEventListener('kimai.activityDelete', handle);
        document.addEventListener('kimai.projectUpdate', handle);
        document.addEventListener('kimai.projectDelete', handle);
        document.addEventListener('kimai.customerUpdate', handle);
        document.addEventListener('kimai.customerDelete', handle);
    }

    emptyList() {
        this.itemList.innerHTML = '';
    }

    setEntries(entries) {
        if (entries.length === 0) {
            this.emptyList();
            return;
        }

        let htmlToInsert = '';

        for (let timesheet of entries) {
            let label = this.attributes['template']
                .replace('%customer%', this.escape(timesheet.project.customer.name))
                .replace('%project%', this.escape(timesheet.project.name))
                .replace('%activity%', this.escape(timesheet.activity.name))
            ;

            htmlToInsert +=
                `<li>` +
                    `<a href="${ this.attributes['href'].replace('000', timesheet.id) }" data-event="kimai.timesheetStart kimai.timesheetUpdate" class="api-link" data-method="PATCH" data-msg-error="timesheet.start.error" data-msg-success="timesheet.start.success">` +
                        `<i class="${ this.attributes['icon'] }"></i> ${ label }` +
                    `</a>` +
                `</li>`;
        }

        this.itemList.innerHTML = htmlToInsert;
    }

    reloadRecentActivities() {
        const self = this;
        const API = this.getContainer().getPlugin('api');

        API.get(this.attributes['api'], {}, function(result) {
            self.setEntries(result);
        });
    }

}
