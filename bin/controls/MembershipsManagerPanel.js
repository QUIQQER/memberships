/**
 * MembershipsManagerPanel JavaScript Control
 *
 * Panel that contains the MembershipsManager
 *
 * @module package/quiqqer/memberships/bin/controls/MembershipsManagerPanel
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/desktop/Panel
 * @require package/quiqqer/memberships/bin/controls/MembershipsManager
 */
define('package/quiqqer/memberships/bin/controls/MembershipsManagerPanel', [

    'qui/controls/desktop/Panel',
    'package/quiqqer/memberships/bin/controls/MembershipsManager',
    'Locale'

], function (QUIPanel, MembershipsManager, QUILocale) {
    "use strict";

    var lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/memberships/bin/controls/MembershipsManagerPanel',

        Binds: [
            '$onCreate',
            '$onResize',
            '$openMembershipPanel'
        ],

        options: {
            title: QUILocale.get(lg, 'controls.membershipsmanagerpanel.title')
        },

        initialize: function (options) {
            this.parent(options);

            this.$Manager = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onResize: this.$onResize
            });
        },

        /**
         * Event: onCreate
         */
        $onCreate: function () {
            this.$Manager = new MembershipsManager({
                showButtons: true,
                events     : {
                    onSelect: this.$openMembershipPanel
                }
            }).inject(this.getContent());
        },

        /**
         * Event: onResize
         */
        $onResize: function() {
            this.$Manager.resize();
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
