/**
 * Select membership duration
 *
 * @module package/quiqqer/memberships/bin/controls/InputDuration
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require qui/controls/buttons/Select
 * @require Locale
 * @require css!package/quiqqer/memberships/bin/controls/InputDuration.css
 */
define('package/quiqqer/memberships/bin/controls/InputDuration', [

    'qui/controls/Control',
    'qui/controls/buttons/Select',
    'qui/controls/loader/Loader',
    'Locale',

    'package/quiqqer/memberships/bin/Memberships',

    'css!package/quiqqer/memberships/bin/controls/InputDuration.css'

], function (QUIControl, QUISelect, QUILoader, QUILocale, Memberships) {
    "use strict";

    var lg = 'quiqqer/memberships';

    return new Class({
        Extends: QUIControl,
        Type   : 'package/quiqqer/memberships/bin/controls/InputDuration',

        Binds: [
            '$onImport',
            'getValue',
            '$setValue',
            '$build'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Container    = null;
            this.$Input        = null;
            this.$InputCount   = null;
            this.$PeriodSelect = null;
            this.$durationMode = 'day';
            this.Loader        = new QUILoader();

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onImport
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
                'class': 'field-container-field',
                html   : '<input type="number" name="quiqqer-memberships-inputduration-count" min="1">'
            }).inject(Elm, 'after');

            this.$Input      = Elm;
            this.$Input.type = 'hidden';

            this.Loader.show();

            Memberships.getSetting('durationMode').then(function (durationMode) {
                self.Loader.hide();
                self.$durationMode = durationMode;
                self.$build();
            });
        },

        /**
         * Build inputs
         */
        $build: function () {
            this.$InputCount = this.$Container.getElement(
                'input[name="quiqqer-memberships-inputduration-count"]'
            );

            this.$InputCount.addEvent('change', this.$setValue);

            this.$PeriodSelect = new QUISelect({
                'class'  : 'quiqqer-memberships-inputduration-period',
                menuTop  : true,
                showIcons: false,
                events   : {
                    onChange: this.$setValue
                }
            }).inject(this.$Container);

            var count  = false,
                period = false;

            if (this.$Input.value !== '') {
                var values = this.$Input.value.split('-');

                count  = values[0];
                period = values[1];
            }

            if (this.$durationMode === 'exact' ||
                period === 'minute' ||
                period === 'hour') {
                this.$PeriodSelect.appendChild(
                    QUILocale.get(lg, 'controls.inputduration.period.minute'),
                    'minute'
                ).appendChild(
                    QUILocale.get(lg, 'controls.inputduration.period.hour'),
                    'hour'
                )
            }

            this.$PeriodSelect.appendChild(
                QUILocale.get(lg, 'controls.inputduration.period.day'),
                'day'
            ).appendChild(
                QUILocale.get(lg, 'controls.inputduration.period.week'),
                'week'
            ).appendChild(
                QUILocale.get(lg, 'controls.inputduration.period.month'),
                'month'
            ).appendChild(
                QUILocale.get(lg, 'controls.inputduration.period.year'),
                'year'
            );

            if (count !== false) {
                this.$InputCount.value = count;
            } else {
                this.$InputCount.value = '1';
            }

            if (period !== false) {
                this.$PeriodSelect.setValue(period);
            } else {
                this.$PeriodSelect.setValue('month');
            }
        },

        /**
         * Return the input value
         *
         * @returns {String}
         */
        getValue: function () {
            return this.$Input.value;
        },

        /**
         * Set value
         */
        $setValue: function () {
            this.$Input.value = this.$InputCount.value + '-' + this.$PeriodSelect.getValue();
        }
    });
});