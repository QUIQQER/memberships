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
         * @param {Array} userIds
         * @return {Promise}
         */
        deleteMembershipUsers: function (userIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_users_delete', resolve, {
                    'package': pkg,
                    userIds  : JSON.encode(userIds),
                    onError  : reject
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
        },

        /**
         * Update MembershipUser
         *
         * @param {Integer} membershipUserId
         * @param {Object} Attributes
         * @return {Promise}
         */
        update: function (membershipUserId, Attributes) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_users_update', resolve, {
                    'package'       : pkg,
                    membershipUserId: membershipUserId,
                    attributes      : JSON.encode(Attributes),
                    onError         : reject
                })
            });
        },

        /**
         * Start the cancellation process for a MembershipUser
         *
         * @param {Integer} membershipUserId
         * @return {Promise}
         */
        startCancel: function (membershipUserId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_users_startCancel', resolve, {
                    'package'       : pkg,
                    membershipUserId: membershipUserId,
                    onError         : reject
                })
            });
        },

        /**
         * Abort the cancellation process for a MembershipUser
         *
         * @param {Integer} membershipUserId
         * @return {Promise}
         */
        abortCancel: function (membershipUserId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_users_abortCancel', resolve, {
                    'package'       : pkg,
                    membershipUserId: membershipUserId,
                    onError         : reject
                })
            });
        },

        /**
         * Get MembershipUser data (some general attribues)
         *
         * @param {Integer} membershipUserId
         * @return {Promise}
         */
        get: function (membershipUserId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_users_get', resolve, {
                    'package'       : pkg,
                    membershipUserId: membershipUserId,
                    onError         : reject
                })
            });
        },

        /**
         * Get all Membership data for the current session user
         *
         * @return {Promise}
         */
        getProfileData: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_users_getProfileData', resolve, {
                    'package': pkg,
                    onError  : reject
                })
            });
        },

        /**
         * Get MembershipUser history
         *
         * @param {Integer} membershipUserId
         * @return {Promise}
         */
        getHistory: function (membershipUserId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_users_getHistory', resolve, {
                    'package'       : pkg,
                    membershipUserId: membershipUserId,
                    onError         : reject
                })
            });
        },

        /**
         * Get/Search memberships (archived)
         *
         * @param {Integer} membershipId
         * @param {Object} SearchParams
         * @return {Promise}
         */
        getArchiveList: function (membershipId, SearchParams) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_users_getArchiveList', resolve, {
                    'package'   : pkg,
                    membershipId: membershipId,
                    searchParams: JSON.encode(SearchParams),
                    onError     : reject
                })
            });
        }
    });
});
