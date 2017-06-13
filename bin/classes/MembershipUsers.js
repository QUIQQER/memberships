/**
 * Membership users handler
 *
 * @module package/quiqqer/memberships/bin/classes/MembershipUsers
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require Ajax
 */
define('package/quiqqer/memberships/bin/classes/MembershipUsers', [

    'Ajax'

], function (QUIAjax) {
    "use strict";

    var pkg = 'quiqqer/memberships';

    return new Class({

        Type: 'package/quiqqer/memberships/bin/classes/MembershipUsers',

        /**
         * Add user(s) to a membership
         *
         * @param {Integer} membershipId
         * @param {Array} userIds
         * @return {Promise}
         */
        addMembershipUsers: function (membershipId, userIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_users_create', resolve, {
                    'package'   : pkg,
                    membershipId: membershipId,
                    userIds     : JSON.encode(userIds),
                    onError     : reject
                })
            });
        },

        /**
         * Delete (multiple) membershipss
         *
         * @param {Integer} membershipId
         * @param {Array} userIds
         * @return {Promise}
         */
        deleteMembershipUsers: function (membershipId, userIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_users_delete', resolve, {
                    'package'   : pkg,
                    membershipId: membershipId,
                    userIds     : JSON.encode(userIds),
                    onError     : reject
                })
            });
        },

        /**
         * Get/Search memberships
         *
         * @param {Integer} membershipId
         * @param {Object} SearchParams
         * @return {Promise}
         */
        getList: function (membershipId, SearchParams) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_users_getList', resolve, {
                    'package'   : pkg,
                    membershipId: membershipId,
                    searchParams: JSON.encode(SearchParams),
                    onError     : reject
                })
            });
        }
    });
});
