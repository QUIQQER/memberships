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
    'utils/Controls',

    'package/quiqqer/memberships/bin/controls/MembershipsManager',

    'css!package/quiqqer/memberships/bin/controls/user/UserMemberships.css'

], function (QUIControl, QUIControlUtils, MembershipsManager) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/memberships/bin/controls/user/UserMemberships',

        Binds: [
            '$onInject',
            '$onResize',
            '$openMembershipPanel'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Manager = null;

            this.addEvents({
                onInject: this.$onInject
            });
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
            var self = this;

            // if control is injected in a panel, register onResize event
            QUIControlUtils.getControlByElement(
                this.$Elm.getParent('.qui-panel')
            ).then(function (Panel) {
                Panel.addEvent('onResize', self.$onResize);
            }, function () {
                // do nothing if no panel found
            });

            this.$Elm.getParent('form').setStyles({
                float : 'left',
                height: '100%',
                width : '100%'
            });
            this.$Elm.addClass('quiqqer-memberships-user-usermemberships');

            (function () {
                var User = self.getAttribute('Panel').getUser();

                self.$Manager = new MembershipsManager({
                    userId     : User.getId(),
                    multiselect: false,
                    showButtons: false,
                    events     : {
                        onSelect: self.$openMembershipPanel
                    }
                }).inject(self.$Elm);
            }).delay(500);
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
