/**
 * Assign a membership to a product
 *
 * @module package/quiqqer/memberships/bin/controls/products/MembershipField
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require controls/users/Entry
 * @require controls/users/search/Window
 * @require package/quiqqer/memberships/bin/Fields
 */
define('package/quiqqer/memberships/bin/controls/products/MembershipField', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',

    'package/quiqqer/memberships/bin/Memberships',
    'package/quiqqer/memberships/bin/controls/MembershipsSearchPopup',

    'Locale',

    'css!package/quiqqer/memberships/bin/controls/products/MembershipField.css'

], function (QUI, QUIControl, QUILoader, Memberships, MembershipsSearchPopup, QUILocale) {
    "use strict";

    var lg = 'quiqqer/memberships';

    return new Class({
        Extends: QUIControl,
        Type   : 'package/quiqqer/memberships/bin/controls/products/MembershipField',

        Binds: [
            '$onImport',
            '$refresh',
            'openMembershipSelect',
            'getValue'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Button  = null;
            this.$Input   = null;
            this.$Display = null;
            this.Loader   = new QUILoader();

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event : on import
         */
        $onImport: function () {
            var Elm = this.getElm();

            Elm.type = 'hidden';

            this.$Button = new Element('span', {
                'class': 'field-container-item',
                html   : '<span class="fa fa-id-card"></span>',
                styles : {
                    cursor    : 'pointer',
                    lineHeight: 30,
                    textAlign : 'center',
                    width     : 50
                },
                events : {
                    click: this.openMembershipSelect
                }
            }).inject(Elm, 'after');

            this.$Display = new Element('div', {
                'class': 'field-container-field quiqqer-memberships-products-membershipfield-display',
            }).inject(Elm, 'before');

            this.Loader.inject(Elm);

            this.$Input = Elm;

            this.$refresh();
        },

        /**
         * Refresh Membership data
         */
        $refresh: function () {
            var self         = this;
            var membershipId = parseInt(this.$Input.value);

            if (!membershipId) {
                this.$Display.set(
                    'html',
                    QUILocale.get(lg,
                        'controls.products.membershipfield.empty.placeholder'
                    )
                );

                return;
            }

            this.Loader.show();

            // load Membership data
            Memberships.getProductFieldData(membershipId).then(function (Membership) {
                self.$Display.set(
                    'html',
                    Membership.title + ' (#' + Membership.id + ')'
                );

                self.Loader.hide();
            });
        },

        /**
         * Open membership select
         */
        openMembershipSelect: function () {
            var self = this;

            new MembershipsSearchPopup({
                events: {
                    onSubmit: function (selected, Popup) {
                        self.$Input.value = selected[0];
                        self.$refresh();
                        Popup.close();
                    }
                }
            }).open();
        },

        /**
         * Return the current value
         *
         * @returns {String}
         */
        getValue: function () {
            return this.$Input.value;
        }
    });
});
