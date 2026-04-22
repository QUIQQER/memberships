/**
 * MembershipUserHistoryPopup JavaScript Control
 *
 * Popup vor viewing the history log of a specific MembershipUser
 */
define('package/quiqqer/memberships/bin/controls/users/MembershipUserHistoryPopup', [

    'qui/controls/windows/SimpleWindow',
    'package/quiqqer/memberships/bin/controls/users/MembershipUserHistory',
    'Locale'

], function (QUISimpleWindow, MembershipUserHistory, QUILocale) {
    "use strict";

    const lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUISimpleWindow,
        Type: 'package/quiqqer/memberships/bin/controls/users/MembershipUserHistoryPopup',

        Binds: [
            '$onOpen'
        ],

        options: {
            membershipUserId: false, // ID of MembershipUser (this is NOT the QUIQQER User ID!)
            maxWidth: 760,
            maxHeight: 620,
            contentPadding: true,
            icon: 'fa fa-history',
            title: QUILocale.get(lg, 'controls.users.membershipuserhistorypopup.title'),
            'class': 'quiqqer-memberships-membershipuserhistorypopup',
            mobileMode: 'popup'
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * Event: onOpen
         */
        $onOpen: function () {
            new MembershipUserHistory({
                membershipUserId: this.getAttribute('membershipUserId')
            }).inject(this.getContent());
        }
    });
});
