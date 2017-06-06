/**
 * Memberships handler
 *
 * @module package/quiqqer/memberships/bin/classes/Memberships
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require Ajax
 */
define('package/quiqqer/memberships/bin/classes/Memberships', [

    'Ajax'

], function (QUIAjax) {
    "use strict";

    var pkg = 'quiqqer/memberships';

    return new Class({

        Type: 'package/quiqqer/memberships/bin/classes/Memberships',

        /**
         * Get data of a single membership
         *
         * @param {Integer} id - Membership ID
         * @return {Promise}
         */
        getMembership: function (id) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_get', resolve, {
                    'package': pkg,
                    id       : id,
                    onError  : reject
                })
            });
        },

        /**
         * Create a new membership
         *
         * @param {String} title
         * @return {Promise}
         */
        createMembership: function (title) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_create', resolve, {
                    'package': pkg,
                    title    : title,
                    onError  : reject
                })
            });
        },

        /**
         * Update an existing membership
         *
         * @param {Integer} membershipId
         * @param {Object} Attributes
         * @return {Promise}
         */
        updateMembership: function (membershipId, Attributes) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_update', resolve, {
                    'package' : pkg,
                    id        : membershipId,
                    attributes: JSON.encode(Attributes),
                    onError   : reject
                })
            });
        },

        /**
         * Delete (multiple) membershipss
         *
         * @param {Array} membershipIds
         * @return {Promise}
         */
        deleteMemberships: function (membershipIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_delete', resolve, {
                    'package'    : pkg,
                    membershipIds: JSON.encode(membershipIds),
                    onError      : reject
                })
            });
        },

        /**
         * Get/Search memberships
         *
         * @param {Object} SearchParams
         * @return {Promise}
         */
        getList: function (SearchParams) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_getList', resolve, {
                    'package'   : pkg,
                    searchParams: JSON.encode(SearchParams),
                    onError     : reject
                })
            });
        },

        /**
         * Get list of packages that are relevant for quiqqer/memberships
         *
         * @return {Promise}
         */
        getInstalledMembershipPackages: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_getInstalledMembershipPackages', resolve, {
                    'package': pkg,
                    onError  : reject
                })
            });
        }
    });
});
