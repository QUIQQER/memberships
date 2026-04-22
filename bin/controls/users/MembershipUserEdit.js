/**
 * MembershipUserEdit JavaScript Control
 *
 * Edit a MembershipUser
 */
define('package/quiqqer/memberships/bin/controls/users/MembershipUserEdit', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',
    'qui/utils/Form',

    'package/quiqqer/memberships/bin/MembershipUsers',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/memberships/bin/controls/users/MembershipUserEdit.html',
    'css!package/quiqqer/memberships/bin/controls/users/MembershipUserEdit.css'

], function (QUIControl, QUILoader, QUIButton, QUIConfirm, QUIFormUtils, MembershipUsersHandler,
             QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    const lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/memberships/bin/controls/users/MembershipUserEdit',

        Binds: [
            '$onInject',
            '$load',
            '$onCreate',
            'submit',
            'refresh'
        ],

        options: {
            membershipUserId: false, // ID of MembershipUser (this is NOT the QUIQQER User ID!)
            showButtons: true
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader = new QUILoader();
            this.$MembershipUser = null;
            this.$cancelled = false;

            this.addEvents({
                onCreate: this.$onCreate,
                onInject: this.$onInject
            });
        },

        /**
         * Event: onCreate
         */
        $onCreate: function () {
            this.$Elm.addClass('quiqqer-memberships-membershipuseredit');
        },

        /**
         * Event: onImport
         */
        $onInject: function () {
            this.Loader.inject(this.$Elm);
            this.refresh();
        },

        /**
         * Refresh data
         */
        refresh: function () {
            this.Loader.show();

            const mUid = this.getAttribute('membershipUserId');

            MembershipUsersHandler.get(mUid).then((MembershipUser) => {
                this.Loader.hide();
                this.$MembershipUser = MembershipUser;
                this.$load();
            });
        },

        /**
         * Create elements
         */
        $load: function () {
            const lgPrefix = 'controls.users.membershipuseredit.template.';

            this.$Elm.innerHTML = Mustache.render(template, {
                header: QUILocale.get(lg, lgPrefix + 'header', {
                    id: this.$MembershipUser.id,
                    name: this.$MembershipUser.fullName
                }),
                labelBeginDate: QUILocale.get(lg, 'controls.membershipusers.tbl.header.beginDate'),
                labelEndDate: QUILocale.get(lg, 'controls.membershipusers.tbl.header.endDate'),
                labelCancelled: QUILocale.get(lg, lgPrefix + 'cancelled')
            });

            if (this.getAttribute('showButtons')) {
                new QUIButton({
                    textimage: 'fa fa-save',
                    text: QUILocale.get(lg, 'controls.users.membershipuseredit.btn.save'),
                    events: {
                        onClick: this.submit
                    }
                }).inject(
                    this.$Elm.querySelector('.quiqqer-memberships-membershipuseredit-submit')
                );
            }

            const Form = this.$Elm.querySelector('form');

            QUIFormUtils.setDataToForm(this.$MembershipUser, Form);

            //if (this.$MembershipUser.infinite) {
            //    Form.getElement('input[name="endDate"]').setStyle('display', 'none');
            //}

            // special cancel trigger
            Form.querySelector('input[name="cancelled"]').addEventListener('change', (event) => {
                if (!this.$MembershipUser.cancelled) {
                    this.$cancelled = event.currentTarget.checked;
                }
            });
        },

        /**
         * Submit MembershipUser data
         *
         * @return {Promise}
         */
        submit: function () {
            const Form = this.$Elm.querySelector('form');

            this.Loader.show();

            return MembershipUsersHandler.update(
                this.$MembershipUser.id,
                QUIFormUtils.getFormData(Form)
            ).then((success) => {
                if (success) {
                    if (this.$cancelled) {
                        this.$confirmCancelMailDialog().then(() => {
                            this.fireEvent('submit', [this]);
                        });
                    } else {
                        this.fireEvent('submit', [this]);
                    }
                }

                this.Loader.hide();
                this.refresh();
            });
        },

        /**
         * Ask the user if a membership termination mail should be sent
         *
         * @return {Promise}
         */
        $confirmCancelMailDialog: function () {
            return new Promise((resolve, reject) => {
                const Popup = new QUIConfirm({
                    'maxHeight': 300,
                    'autoclose': true,

                    'information': QUILocale.get(lg,
                        'controls.users.membershipuseredit.cancelmail.popup.info'
                    ),
                    'title': QUILocale.get(lg, 'controls.users.membershipuseredit.cancelmail.popup.title'),
                    'texticon': 'fa fa-mail',
                    'icon': 'fa fa-mail',

                    cancel_button: {
                        text: false,
                        textimage: 'icon-remove fa fa-remove'
                    },
                    ok_button: {
                        text: false,
                        textimage: 'icon-ok fa fa-check'
                    },
                    events: {
                        onSubmit: () => {
                            Popup.Loader.show();

                            QUIAjax.post(
                                'package_quiqqer_memberships_ajax_memberships_users_sendCancelMail',
                                function () {
                                    Popup.close();
                                },
                                {
                                    'package': 'quiqqer/memberships',
                                    membershipUserId: this.$MembershipUser.id,
                                    onError: reject
                                }
                            );
                        },
                        onClose: () => {
                            resolve();
                        }
                    }
                });

                Popup.open();
            });
        }
    });
});
