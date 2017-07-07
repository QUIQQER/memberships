/**
 * UserProfile JavaScript Control
 *
 * View data from archived membership users
 *
 * @module package/quiqqer/memberships/bin/controls/profile/UserProfile
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require qui/controls/loader/Loader
 * @require qui/controls/windows/Popup
 * @require qui/controls/windows/Confirm
 * @require qui/controls/buttons/Button
 * @require utils/Controls
 * @require controls/grid/Grid
 * @require package/quiqqer/memberships/bin/Licenses
 * @require package/quiqqer/memberships/bin/controls/LicenseBundles
 * @require Locale
 * @require Ajax
 * @require Mustache
 * @require text!package/quiqqer/memberships/bin/controls/profile/UserProfile.html
 * @require css!package/quiqqer/memberships/bin/controls/profile/UserProfile.css
 */
define('package/quiqqer/memberships/bin/controls/profile/UserProfile', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Popup',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Button',

    'utils/Controls',

    'package/quiqqer/memberships/bin/Memberships',
    'package/quiqqer/memberships/bin/MembershipUsers',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/memberships/bin/controls/profile/UserProfile.html',
    'css!package/quiqqer/memberships/bin/controls/profile/UserProfile.css'

], function (QUIControl, QUILoader, QUIPopup, QUIConfirm, QUIButton,
             QUIControlUtils, Memberships, MembershipUsersHandler,
             QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/memberships/bin/controls/profile/UserProfile',

        Binds: [
            '$onInject',
            '$onResize',
            '$onCreate',
            'refresh',
            '$build'
        ],

        options: {
            membershipId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader       = new QUILoader();
            this.$memberships = [];

            this.addEvents({
                onCreate: this.$onCreate,
                onInject: this.$onInject,
                onResize: this.$onResize
            });
        },

        /**
         * Event: onCreate
         */
        $onCreate: function () {

        },

        /**
         * Event: onImport
         */
        $onInject: function () {
            this.$Elm.addClass('quiqqer-memberships-membershipusersarchive');

            var lgPrefix = 'controls.profile.userprofile.template.';

            this.$Elm.set('html', Mustache.render(template, {
                header              : QUILocale.get(lg, lgPrefix + 'header'),
                headerMembership    : QUILocale.get(lg, lgPrefix + 'headerMembership'),
                headerMembershipData: QUILocale.get(lg, lgPrefix + 'headerMembershipData')
            }));

            this.Loader.inject(this.$Elm);

            this.refresh();
        },

        /**
         * event: onResize
         */
        $onResize: function () {
            // @todo
        },

        /**
         * Refresh control data
         */
        refresh: function () {
            var self = this;

            this.Loader.show();

            MembershipUsersHandler.getSessionUserData().then(function (memberships) {
                self.Loader.hide();
                self.$memberships = memberships;
                self.$build();
            });
        },

        /**
         * Fill table with membership data
         */
        $build: function () {
            var self         = this;
            var lgPrefix     = 'controls.profile.userprofile.datatable.';
            var TableBodyElm = this.$Elm.getElement(
                '.quiqqer-memberships-profile-userprofile-table tbody'
            );

            for (var i = 0, len = this.$memberships.length; i < len; i++) {
                var Membership = this.$memberships[i];
                var Row        = new Element('tr').inject(TableBodyElm);

                // Membership title and description
                new Element('td', {
                    html: '<h2>' + Membership.membershipTitle + '</h2>' +
                    '<p>' + Membership.membershipShort + '</p>'
                }).inject(Row);

                // Membership data (dates and status)
                var status = 'active';

                if (Membership.cancelled) {
                    status = 'cancelled';
                } else if (Membership.cancelDate) {
                    status = 'cancelled_start';
                }

                new Element('td', {
                    html: '<table>' +
                    '<tr>' +
                    '<td>' + QUILocale.get(lg, lgPrefix + 'labelAddedDate') + '</td>' +
                    '<td>' + Membership.addedDate + '</td>' +
                    '</tr>' +
                    '<tr>' +
                    '<td>' + QUILocale.get(lg, lgPrefix + 'labelStatus') + '</td>' +
                    '<td><span class="quiqqer-memberships-profile-userprofile-status-' + status + '">'
                    + QUILocale.get(lg, lgPrefix + 'status.' + status) + '</span></td>' +
                    '</tr>' +
                    '</table>'
                }).inject(Row);
            }
        },

        $getCancelBtn: function()
        {

        },

        $getAbortCancelBtn: function()
        {

        }
    });
});
