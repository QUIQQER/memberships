/**
 * Display all memberships of a User in the User panel
 *
 * @module package/quiqqer/memberships/bin/controls/user/UserMemberships
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require package/quiqqer/memberships/bin/controls/MembershipsManager
 */
define('package/quiqqer/memberships/bin/controls/user/UserMemberships', [

    'qui/controls/Control',

    'package/quiqqer/memberships/bin/controls/MembershipsManager',

    'css!package/quiqqer/memberships/bin/controls/user/UserMemberships.css'

], function (QUIControl, MembershipsManager) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/memberships/bin/controls/user/UserMemberships',

        Binds: [
            '$onImport',
            '$onInject',
            '$onResize',
            '$onSetAttribute',
            '$onDestroy',
            '$openMembershipPanel'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Manager = null;
            this.$Panel = null;

            this.addEvents({
                onImport: this.$onImport,
                onInject: this.$onInject,
                onSetAttribute: this.$onSetAttribute,
                onDestroy: this.$onDestroy
            });
        },

        /**
         * Replace the XML settings placeholder with the full-height membership list.
         */
        $onImport: function () {
            const Input = this.getElm();
            const Table = Input.closest('table');
            const Container = document.createElement('div');

            Container.setAttribute('data-quiid', this.getId());
            Container.setAttribute('data-name', 'user-memberships');
            (Table || Input).replaceWith(Container);
            this.$Elm = Container;
            this.$onInject();
        },

        /**
         * The user panel is assigned after XML controls have been imported.
         */
        $onSetAttribute: function (name, Panel) {
            if (name !== 'Panel' || Panel === this.$Panel) {
                return;
            }

            this.$onDestroy();
            this.$Panel = Panel;

            if (Panel) {
                Panel.addEvent('onResize', this.$onResize);
                this.$onResize();
            }
        },

        $onDestroy: function () {
            if (this.$Panel) {
                this.$Panel.removeEvent('onResize', this.$onResize);
                this.$Panel = null;
            }
        },

        /**
         * Event: onResize
         */
        $onResize: function () {
            if (this.$Manager) {
                this.$Manager.resize();
            }
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            const Container = this.getElm();
            const Form = Container.closest('form');
            const Panel = this.getAttribute('Panel');
            const userId = this.getAttribute('uid') || (Panel && Panel.getUser().getId());

            if (Form) {
                Object.assign(Form.style, {float: 'left', height: '100%', width: '100%'});
            }

            Container.classList.add('quiqqer-memberships-user-usermemberships');

            // Never fall back to the manager's unfiltered list without a user ID.
            if (!userId || this.$Manager) {
                return;
            }

            this.$Manager = new MembershipsManager({
                userId: userId,
                multiselect: false,
                showButtons: false,
                events: {
                    onSelect: this.$openMembershipPanel
                }
            }).inject(Container);

            if (Panel) {
                this.$onSetAttribute('Panel', Panel);
            }
        },

        /**
         * Opens a panel for a single membership
         *
         * @param {Number} membershipId
         */
        $openMembershipPanel: function (membershipId) {
            require([
                'package/quiqqer/memberships/bin/controls/Membership',
                'utils/Panels'
            ], function (MembershipPanel, Utils) {
                Utils.openPanelInTasks(new MembershipPanel({
                    id   : membershipId,
                    '#id': 'quiqqer_location_' + membershipId
                }));
            });
        }
    });
});
