/**
 * Main control for product settings
 *
 * @module package/quiqqer/memberships/bin/settings/ProductSettings
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require qui/controls/loader/Loader
 * @require Locale
 * @require package/quiqqer/memberships/bin/Memberships
 * @require css!package/quiqqer/memberships/bin/controls/settings/ProductSettings.css
 */
define('package/quiqqer/memberships/bin/controls/settings/ProductSettings', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'Locale',

    'package/quiqqer/memberships/bin/Memberships',

    'css!package/quiqqer/memberships/bin/controls/settings/ProductSettings'

], function (QUIControl, QUILoader, QUILocale, Memberships) {
    "use strict";

    var lg = 'quiqqer/memberships';

    return new Class({
        Extends: QUIControl,
        Type   : 'package/quiqqer/memberships/bin/settings/ProductSettings',

        Binds: [
            '$onImport',
            '$loadSettingControl'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Container = null;
            this.$Input     = null;
            this.Loader     = new QUILoader();

            this.addEvents({
                onImport: this.$onImport
            });
        },

        create: function () {
            this.$Elm = new Element('input', {
                type : 'hidden',
                value: this.getAttribute('value'),
                name : this.getAttribute('name')
            });

            this.Loader.inject(this.$Elm);

            return this.$Elm;
        },

        /**
         * event : on import
         */
        $onImport: function () {
            var self = this;
            var Elm  = this.getElm();

            this.$Container = new Element('div', {
                'class': 'field-container-field quiqqer-memberships-settings-productsettings-container'
            }).inject(Elm, 'after');

            this.$Input      = Elm;
            this.$Input.type = 'hidden';

            this.Loader.show();

            Memberships.getInstalledMembershipPackages().then(function (packages) {
                if (!packages.contains('quiqqer/products')) {
                    self.$Container.set(
                        'html',
                        QUILocale.get(lg,
                            'controls.settings.productsettings.package.required'
                        )
                    );

                    return;
                }

                self.$loadSettingControl();
            });
        },

        /**
         * Load setting control
         */
        $loadSettingControl: function () {
            var self                     = this;
            var control, ControlSettings = {};

            switch (this.$Input.get('name')) {
                case 'products.categoryId':
                    control         = 'package/quiqqer/products/bin/controls/categories/Select';
                    ControlSettings = {
                        max     : 1,
                        multiple: false
                    };
                    break;

                default:
                    return;
            }

            this.$Input.inject(this.$Elm, 'after');

            //this.$Input.set({
            //    'data-qui'                 : control,
            //    'data-qui-options-max'     : 1,
            //    'data-qui-options-multiple': 0
            //});
            //
            //QUI.parse(this.getElm()).then(function() {
            //
            //});

            this.$Container.setStyle('display', 'none');
            this.Loader.show();

            require([control], function (SettingControl) {
                self.Loader.hide();
                new SettingControl(ControlSettings).imports(self.$Input);
            });
        }
    });
});