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
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',

    'package/quiqqer/memberships/bin/MembershipUsers',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/memberships/bin/controls/profile/UserProfile.html',
    'text!package/quiqqer/memberships/bin/controls/profile/UserProfile.Membership.html',
    'css!package/quiqqer/memberships/bin/controls/profile/UserProfile.css'

], function (QUIControl, QUILoader, QUIConfirm, QUIPopup, QUIButton, MembershipUsers,
             QUILocale, QUIAjax, Mustache, template, membershipTemplate) {
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
            '$openAbortCancelConfirm',
            '$getMembershipElm'
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

            MembershipUsers.getProfileData().then(function (memberships) {
                self.Loader.hide();
                self.$memberships = memberships;
                self.$build();
            });
        },

        /**
         * Fill table with membership data
         */
        $build: function () {
            var self           = this;
            var MembershipsElm = this.$Elm.getElement(
                '.quiqqer-memberships-profile-userprofile-memberships'
            );

            var InfoElm = this.$Elm.getElement(
                '.quiqqer-memberships-profile-userprofile-info'
            );

            // if user has no memberships, hide table and show info
            if (!this.$memberships.length) {
                InfoElm.set('html', QUILocale.get(lg,
                    'controls.profile.userprofile.info.no.memberships'
                ));

                return;
            }

            for (var i = 0, len = this.$memberships.length; i < len; i++) {
                var Membership = this.$memberships[i];
                this.$getMembershipElm(Membership).inject(MembershipsElm);
            }
        },

        /**
         * Get HTMLElement that represents the Membership data
         *
         * @param {Object} Membership
         * @return {HTMLElement}
         */
        $getMembershipElm: function (Membership) {
            var self     = this;
            var status   = 'active';
            var lgPrefix = 'controls.profile.userprofile.datatable.';

            if (Membership.cancelled) {
                status = 'cancelled';
            } else if (Membership.cancelDate) {
                status = 'cancelled_start';
            }

            var endDateLabel;

            if (Membership.autoExtend) {
                endDateLabel = QUILocale.get(lg, lgPrefix + 'labelEndDate.autoExtend');
            } else {
                endDateLabel = QUILocale.get(lg, lgPrefix + 'labelEndDate.noAutoExtend');
            }

            var MembershipElm = new Element('div', {
                'class': 'quiqqer-memberships-profile-userprofile-membership grid-100',
                html   : Mustache.render(membershipTemplate, {
                    membershipTitle: Membership.membershipTitle,
                    membershipShort: Membership.membershipShort,
                    labelAddedDate : QUILocale.get(lg, lgPrefix + 'labelAddedDate'),
                    addedDate      : Membership.addedDate,
                    labelEndDate   : endDateLabel,
                    endDate        : Membership.endDate,
                    labelStatus    : QUILocale.get(lg, lgPrefix + 'labelStatus'),
                    status         : '<span class="quiqqer-memberships-profile-userprofile-status-'
                    + status + '">' + QUILocale.get(lg, lgPrefix + 'status.' + status) +
                    '</span>'
                })
            });

            // show content btn
            if (Membership.membershipContent !== '') {
                var ShowContentElm = MembershipElm.getElement(
                    '.quiqqer-memberships-profile-userprofile-membership-info-title'
                );

                new Element('span', {
                    'class': 'fa fa-info-circle quiqqer-memberships-profile-userprofile-membership-info-content',
                    events : {
                        click: function () {
                            self.$showMembershipContent(Membership);
                        }
                    }
                }).inject(ShowContentElm);
            }

            // only show "cancel" and "withdraw cancellation" btns on autoextend
            if (!Membership.autoExtend) {
                return MembershipElm;
            }

            // if autoextend and not cancelled -> hide endDate
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
                    MembershipElm.getElement(
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
                    MembershipElm.getElement(
                        '.quiqqer-memberships-profile-userprofile-btn'
                    )
                );
            }

            return MembershipElm;
        },

        /**
         * Show Membership Content
         *
         * @param {Object} Membership
         */
        $showMembershipContent: function (Membership) {
            new QUIPopup({
                content: Membership.membershipContent,
                icon   : 'fa fa-id-card-o',
                title  : QUILocale.get(lg,
                    'controls.profile.userprofile.showcontent.title', {
                        title: Membership.membershipTitle
                    }
                )
            }).open();
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
