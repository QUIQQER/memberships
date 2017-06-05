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
         * Get membershipss for user
         *
         * @param {Integer} userId
         * @return {Promise}
         */
        getMemberships: function (userId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_getList', resolve, {
                    'package': pkg,
                    userId   : userId,
                    onError  : reject
                })
            });
        },

        /**
         * Get memberships
         *
         * @param {Integer} membershipsId
         * @return {Promise}
         */
        getLicense: function (membershipsId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_memberships_get', resolve, {
                    'package': pkg,
                    membershipsId: membershipsId,
                    onError  : reject
                })
            });
        },

        /**
         * Create a new memberships
         *
         * @param {String} title
         * @param {Integer} userId
         * @return {Promise}
         */
        createLicense: function (title, userId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_create', resolve, {
                    'package': pkg,
                    userId   : userId,
                    title    : title,
                    onError  : reject
                })
            });
        },

        /**
         * Create a new memberships
         *
         * @param {Integer} membershipsId
         * @param {Object} Attributes
         * @return {Promise}
         */
        editLicense: function (membershipsId, Attributes) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_edit', resolve, {
                    'package' : pkg,
                    membershipsId : membershipsId,
                    attributes: JSON.encode(Attributes),
                    onError   : reject
                })
            });
        },

        /**
         * Delete (multiple) membershipss
         *
         * @param {Array} membershipsIds
         * @return {Promise}
         */
        deleteMemberships: function (membershipsIds) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_delete', resolve, {
                    'package' : pkg,
                    membershipsIds: JSON.encode(membershipsIds),
                    onError   : reject
                })
            });
        },

        /**
         * Get all available packages
         *
         * @return {Promise}
         */
        getPackageList: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_memberships_ajax_packages_getList', resolve, {
                    'package': pkg,
                    onError  : reject
                })
            });
        }
    });
});
