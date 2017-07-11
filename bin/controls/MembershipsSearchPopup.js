/**
 * MembershipsSearchPopup JavaScript Control
 *
 * Popup that contains the MembershipsManager
 *
 * @module package/quiqqer/memberships/bin/controls/MembershipsSearchPopup
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/desktop/Panel
 * @require package/quiqqer/memberships/bin/controls/MembershipsManager
 *
 * @event onSubmit [selectedMembershipIds, this]
 */
define('package/quiqqer/memberships/bin/controls/MembershipsSearchPopup', [

    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',

    'package/quiqqer/memberships/bin/controls/MembershipsManager',

    'Locale'

], function (QUIPopup, QUIButton, MembershipsManager, QUILocale) {
    "use strict";

    var lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/quiqqer/memberships/bin/controls/MembershipsSearchPopup',

        Binds: [
            '$onCreate',
            '$onResize',
            '$submit'
        ],

        options: {
            maxWidth   : 900,
            maxHeight  : 600,
            icon       : 'fa fa-id-card-o',
            title      : QUILocale.get(lg, 'controls.membershipssearchpopup.title'),
            buttons    : true,
            closeButton: true
        },

        initialize: function (options) {
            this.parent(options);

            this.$Manager = null;

            this.addEvents({
                onOpen  : this.$onOpen,
                onResize: this.$onResize
            });
        },

        /**
         * Event: onOpen
         */
        $onOpen: function () {
            this.addButton(new QUIButton({
                text     : 'Auswahl bestätigen',
                textimage: 'fa fa-check',
                events   : {
                    onClick: this.$submit
                }
            }));

            this.$Manager = new MembershipsManager({
                showButtons: false,
                multiselect: false,
                events     : {
                    onSelect: this.$submit
                }
            }).inject(this.getContent());

            this.$Manager.resize();
        },

        /**
         * Submit Membership selection
         */
        $submit: function () {
            this.fireEvent('submit', [this.$Manager.getSelectedIds(), this]);
        },

        /**
         * Event: onResize
         */
        $onResize: function () {
            if (this.$Manager) {
                this.$Manager.resize();
            }
        }
    });
});
