/**
 * MembershipUserEdit JavaScript Control
 *
 * Edit a MembershipUser
 *
 * @module package/quiqqer/memberships/bin/controls/users/MembershipUserEdit
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require qui/controls/loader/Loader
 * @require qui/controls/buttons/Button
 * @require qui/utils/Form
 * @require package/quiqqer/memberships/bin/MembershipUsers
 * @require Locale
 * @require Ajax
 * @require Mustache
 * @require text!package/quiqqer/memberships/bin/controls/users/MembershipUserEdit.html
 * @require css!package/quiqqer/memberships/bin/controls/users/MembershipUserEdit.css
 */
define('package/quiqqer/memberships/bin/controls/users/MembershipUserEdit', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/buttons/Button',
    'qui/utils/Form',

    'package/quiqqer/memberships/bin/MembershipUsers',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/memberships/bin/controls/users/MembershipUserEdit.html',
    'css!package/quiqqer/memberships/bin/controls/users/MembershipUserEdit.css'

], function (QUIControl, QUILoader, QUIButton, QUIFormUtils, MembershipUsersHandler,
             QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/memberships/bin/controls/users/MembershipUserEdit',

        Binds: [
            '$onInject',
            '$load',
            '$onCreate',
            '$submit'
        ],

        options: {
            membershipUserId: false // ID of MembershipUser (this is NOT the QUIQQER User ID!)
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader          = new QUILoader();
            this.$MembershipUser = null;

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
            var self = this;
            this.Loader.show();

            var mUid = this.getAttribute('membershipUserId');

            MembershipUsersHandler.get(mUid).then(function (MembershipUser) {
                self.Loader.hide();
                self.$MembershipUser = MembershipUser;
                self.$load();
            });
        },

        /**
         * Create elements
         */
        $load: function () {
            var lgPrefix = 'controls.users.membershipuseredit.template.';

            this.$Elm.set('html', Mustache.render(template, {
                header        : QUILocale.get(lg, lgPrefix + 'header', {
                    id  : this.$MembershipUser.id,
                    name: this.$MembershipUser.fullName
                }),
                labelBeginDate: QUILocale.get(lg, 'controls.membershipusers.tbl.header.beginDate'),
                labelEndDate  : QUILocale.get(lg, 'controls.membershipusers.tbl.header.endDate')
            }));

            new QUIButton({
                textimage: 'fa fa-save',
                text     : QUILocale.get(lg, 'controls.users.membershipuseredit.btn.save'),
                events   : {
                    onClick: this.$submit
                }
            }).inject(
                this.$Elm.getElement('.quiqqer-memberships-membershipuseredit-submit')
            );

            var Form = this.$Elm.getElement('form');

            QUIFormUtils.setDataToForm(this.$MembershipUser, Form);
        },

        /**
         * Submit MembershipUser data
         */
        $submit: function () {
            var self = this;
            var Form = this.$Elm.getElement('form');

            this.Loader.show();

            MembershipUsersHandler.update(
                this.$MembershipUser.id,
                QUIFormUtils.getFormData(Form)
            ).then(function (success) {
                if (success) {
                    self.fireEvent('submit', [self]);
                }

                self.Loader.hide();
                self.refresh();
            });
        }
    });
});
