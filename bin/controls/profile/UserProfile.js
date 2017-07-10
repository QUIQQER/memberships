/**
 * UserProfile JavaScript Control
 *
 * View membership data of currently logged in user
 *
 * @module package/quiqqer/memberships/bin/controls/profile/UserProfile
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require qui/controls/loader/Loader
 * @require qui/controls/windows/Confirm
 * @require qui/controls/buttons/Button
 * @require package/quiqqer/memberships/bin/MembershipUsers
 * @require Locale
 * @require Ajax
 * @require Mustache
 * @require text!package/quiqqer/memberships/bin/controls/profile/UserProfile.html
 * @require text!package/quiqqer/memberships/bin/controls/profile/UserProfile.MembershipStatus.html
 * @require css!package/quiqqer/memberships/bin/controls/profile/UserProfile.css
 */
define('package/quiqqer/memberships/bin/controls/profile/UserProfile', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Button',

    'package/quiqqer/memberships/bin/MembershipUsers',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/memberships/bin/controls/profile/UserProfile.html',
    'text!package/quiqqer/memberships/bin/controls/profile/UserProfile.MembershipStatus.html',
    'css!package/quiqqer/memberships/bin/controls/profile/UserProfile.css'

], function (QUIControl, QUILoader, QUIConfirm, QUIButton, MembershipUsers,
             QUILocale, QUIAjax, Mustache, template, statusTemplate) {
    "use strict";

    var lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/memberships/bin/controls/profile/UserProfile',

        Binds: [
            '$onInject',
            'refresh',
            '$build',
            '$openCancelConfirm',
            '$openAbortCancelConfirm'
        ],

        options: {
            membershipId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader       = new QUILoader();
            this.$memberships = [];

            this.addEvents({
                onInject: this.$onInject
            });
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
         * Refresh control data
         */
        refresh: function () {
            var self = this;

            this.Loader.show();

            MembershipUsers.getSessionUserData().then(function (memberships) {
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

            var TableElm = this.$Elm.getElement(
                '.quiqqer-memberships-profile-userprofile-table'
            );

            var InfoElm = this.$Elm.getElement(
                '.quiqqer-memberships-profile-userprofile-info'
            );

            // if user has no memberships, hide table and show info
            if (!this.$memberships.length) {
                TableElm.addClass('quiqqer-memberships-profile-userprofile-table__hidden');
                InfoElm.set('html', QUILocale.get(lg, 'controls.profile.userprofile.info.no.memberships'));

                return;
            }

            InfoElm.set('html', '');
            TableElm.removeClass('quiqqer-memberships-profile-userprofile-table__hidden');

            var TableBodyElm = TableElm.getElement('tbody');

            TableBodyElm.set('html', '');

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

                var StatusElm = new Element('td', {
                    html: Mustache.render(statusTemplate, {
                        labelAddedDate: QUILocale.get(lg, lgPrefix + 'labelAddedDate'),
                        addedDate     : Membership.addedDate,
                        labelEndDate  : QUILocale.get(lg, lgPrefix + 'labelEndDate'),
                        endDate       : Membership.endDate,
                        labelStatus   : QUILocale.get(lg, lgPrefix + 'labelStatus'),
                        status        : '<span class="quiqqer-memberships-profile-userprofile-status-'
                        + status + '">' + QUILocale.get(lg, lgPrefix + 'status.' + status) +
                        '</span>'
                    })
                }).inject(Row);

                // only show "cancel" and "withdraw cancellation" btns on autoextend
                if (!Membership.autoExtend) {
                    continue;
                }

                // if autoextend and not cancelled -> hide endDate
                if (!Membership.cancelled) {
                    StatusElm.getElement(
                        '.quiqqer-memberships-profile-userprofile-table-status-enddate'
                    ).addClass('quiqqer-memberships-profile-userprofile-table__hidden');
                }

                if (status === 'active') {
                    // cancel btn
                    new QUIButton({
                        membership: Membership,
                        text      : QUILocale.get(lg, 'controls.profile.userprofile.btn.cancel.text'),
                        'class'   : 'btn-red',
                        events    : {
                            onClick: function (Btn) {
                                self.$openCancelConfirm(
                                    Btn.getAttribute('membership')
                                );
                            }
                        }
                    }).inject(
                        StatusElm.getElement(
                            '.quiqqer-memberships-profile-userprofile-btn'
                        )
                    );
                } else {
                    // abort cancel btn
                    new QUIButton({
                        membership: Membership,
                        text      : QUILocale.get(lg, 'controls.profile.userprofile.btn.abortcancel.text'),
                        'class'   : 'btn-orange',
                        events    : {
                            onClick: function (Btn) {
                                self.$openAbortCancelConfirm(
                                    Btn.getAttribute('membership')
                                );
                            }
                        }
                    }).inject(
                        StatusElm.getElement(
                            '.quiqqer-memberships-profile-userprofile-btn'
                        )
                    );
                }
            }
        },

        /**
         * Open confirm dialog for membership cancellation
         *
         * @param {Object} Membership
         */
        $openCancelConfirm: function (Membership) {
            var self = this;

            new QUIConfirm({
                'maxHeight': 350,
                autoclose  : false,

                text: QUILocale.get(lg, 'controls.profile.userprofile.cancelconfirm.text', {
                    title: Membership.membershipTitle
                }),

                information: QUILocale.get(lg, 'controls.profile.userprofile.cancelconfirm.info', {
                    title  : Membership.membershipTitle,
                    endDate: Membership.endDate
                }),
                'title'    : QUILocale.get(lg, 'controls.profile.userprofile.cancelconfirm.title'),
                'texticon' : 'icon-remove fa fa-ban',
                'icon'     : 'icon-remove fa fa-ban',

                cancel_button: {
                    text     : QUILocale.get(lg, 'controls.profile.userprofile.cancelconfirm.cancel'),
                    textimage: 'icon-remove fa fa-remove'
                },
                ok_button    : {
                    text     : QUILocale.get(lg, 'controls.profile.userprofile.cancelconfirm.ok'),
                    textimage: 'icon-ok fa fa-ban'
                },

                events: {
                    onSubmit: function (Popup) {
                        Popup.Loader.show();

                        MembershipUsers.startCancel(Membership.id).then(function (success) {
                            Popup.close();
                            self.refresh();
                        });
                    }
                }
            }).open();
        },

        /**
         * Open confirm dialog for withdrawal of membership cancellation
         *
         * @param {Object} Membership
         */
        $openAbortCancelConfirm: function (Membership) {
            var self = this;

            new QUIConfirm({
                'maxHeight': 350,
                autoclose  : false,

                text: QUILocale.get(lg, 'controls.profile.userprofile.abortcancel.text', {
                    title: Membership.membershipTitle
                }),

                information: QUILocale.get(lg, 'controls.profile.userprofile.abortcancel.info', {
                    title  : Membership.membershipTitle,
                    endDate: Membership.endDate
                }),
                'title'    : QUILocale.get(lg, 'controls.profile.userprofile.abortcancel.title'),
                'texticon' : 'icon-remove fa fa-ban',
                'icon'     : 'icon-remove fa fa-ban',

                cancel_button: {
                    text     : QUILocale.get(lg, 'controls.profile.userprofile.abortcancel.cancel'),
                    textimage: 'icon-remove fa fa-remove'
                },
                ok_button    : {
                    text     : QUILocale.get(lg, 'controls.profile.userprofile.abortcancel.ok'),
                    textimage: 'icon-ok fa fa-ban'
                },

                events: {
                    onSubmit: function (Popup) {
                        Popup.Loader.show();

                        MembershipUsers.abortCancel(Membership.id).then(function (success) {
                            Popup.close();
                            self.refresh();
                        });
                    }
                }
            }).open();
        }
    });
});
