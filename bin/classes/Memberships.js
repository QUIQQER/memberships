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
         * Get data of Membership
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
         * Get view data of a Membership
         *
         * @param {Integer} id - Membership ID
         * @return {Promise}
         */
        getMembershipView: function(id) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_getView', resolve, {
                    'package': pkg,
                    id       : id,
                    onError  : reject
                })
            });
        },

        /**
         * Get Membership data for product field
         *
         * @param {Number} membershipId
         * @return {Promise}
         */
        getProductFieldData: function (membershipId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_products_getFieldData', resolve, {
                    'package'   : pkg,
                    membershipId: membershipId,
                    onError     : reject
                })
            });
        },

        /**
         * Get all Products that have a Membership assigned
         *
         * @param membershipId
         * @return {Promise}
         */
        getProducts: function (membershipId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_products_getMembershipProducts', resolve, {
                    'package'   : pkg,
                    membershipId: membershipId,
                    onError     : reject
                })
            });
        },

        /**
         * Create a Product from a Membership
         *
         * @param membershipId
         * @return {Promise}
         */
        createProduct: function(membershipId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_products_createMembershipProducts', resolve, {
                    'package'   : pkg,
                    membershipId: membershipId,
                    onError     : reject
                })
            });
        },

        /**
         * Create a new membership
         *
         * @param {String} title
         * @param {Array} groupIds
         * @return {Promise}
         */
        createMembership: function (title, groupIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_create', resolve, {
                    'package': pkg,
                    title    : title,
                    groupIds : JSON.encode(groupIds),
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
        },

        /**
         * Get membership setting
         *
         * @param {String} key - setting key
         * @return {Promise}
         */
        getSetting: function (key) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_getSetting', resolve, {
                    'package': pkg,
                    key      : key,
                    onError  : reject
                })
            });
        }
    });
});
