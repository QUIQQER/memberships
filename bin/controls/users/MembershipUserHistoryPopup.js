/**
 * MembershipUserHistoryPopup JavaScript Control
 *
 * Popup vor viewing the history log of a specific MembershipUser
 */
define('package/quiqqer/memberships/bin/controls/users/MembershipUserHistoryPopup', [

    'qui/controls/windows/Popup',
    'package/quiqqer/memberships/bin/controls/users/MembershipUserHistory',
    'Locale'

], function (QUIPopup, MembershipUserHistory, QUILocale) {
    "use strict";

    const lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIPopup,
        Type: 'package/quiqqer/memberships/bin/controls/users/MembershipUserHistoryPopup',

        Binds: [
            '$onOpen'
        ],

        options: {
            membershipUserId: false, // ID of MembershipUser (this is NOT the QUIQQER User ID!)
            maxWidth: 550,
            maxHeight: 500,
            icon: 'fa fa-history',
            title: QUILocale.get(lg, 'controls.users.membershipuserhistorypopup.title'),
            'class': 'quiqqer-memberships-membershipuserhistorypopup',

            // buttons
            closeButtonText: QUILocale.get('quiqqer/translator', 'edit.btn.close')
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
