/**
 * MembershipUsersArchive JavaScript Control
 *
 * View data from archived membership users
 *
 * @module package/quiqqer/memberships/bin/controls/users/MembershipUsersArchive
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require qui/controls/loader/Loader
 * @require qui/controls/windows/Popup
 * @require qui/controls/windows/Confirm
 * @require qui/controls/buttons/Button
 * @require utils/Controls
 * @require controls/grid/Grid
 * @require package/quiqqer/memberships/bin/Licenses
 * @require package/quiqqer/memberships/bin/controls/LicenseBundles
 * @require Locale
 * @require Ajax
 * @require Mustache
 * @require text!package/quiqqer/memberships/bin/controls/users/MembershipUsersArchive.html
 * @require css!package/quiqqer/memberships/bin/controls/users/MembershipUsersArchive.css
 */
define('package/quiqqer/memberships/bin/controls/users/MembershipUsersArchive', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Popup',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Button',

    'qui/utils/Form',
    'utils/Controls',
    'controls/grid/Grid',
    'controls/users/search/Window',

    'package/quiqqer/memberships/bin/Memberships',
    'package/quiqqer/memberships/bin/MembershipUsers',
    'package/quiqqer/memberships/bin/controls/users/MembershipUserHistory',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/memberships/bin/controls/users/MembershipUsersArchive.html',
    'css!package/quiqqer/memberships/bin/controls/users/MembershipUsersArchive.css'

], function (QUIControl, QUILoader, QUIPopup, QUIConfirm, QUIButton, QUIFormUtils,
             QUIControlUtils, Grid, UserSearchWindow, Memberships, MembershipUsersHandler,
             MembershipUserHistory, QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/memberships/bin/controls/users/MembershipUsersArchive',

        Binds: [
            '$onInject',
            '$onResize',
            '$listRefresh',
            '$setGridData',
            'refresh',
            '$showHistory'
        ],

        options: {
            membershipId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader      = new QUILoader();
            this.$User       = null;
            this.$Grid       = null;
            this.$GridParent = null;
            this.$FormParent = null;
            this.$Membership = null;

            this.addEvents({
                onInject: this.$onInject,
                onResize: this.$onResize
            });
        },

        /**
         * Event: onImport
         */
        $onInject: function () {
            var self = this;

            this.$Elm.addClass('quiqqer-memberships-membershipusersarchive');

            this.Loader.inject(this.$Elm);
            this.Loader.show();

            // if control is injected in a panel, register onResize event
            QUIControlUtils.getControlByElement(
                this.$Elm.getParent('.qui-panel')
            ).then(function (Panel) {
                Panel.addEvent('onResize', self.$onResize);
            }, function () {
                // do nothing if no panel found
            });

            Memberships.getMembership(this.getAttribute('membershipId')).then(function (Membership) {
                self.Loader.hide();
                self.$Membership = Membership;
                self.$load();
            });
        },

        /**
         * event: onResize
         */
        $onResize: function () {
            if (this.$Grid && this.$GridParent) {
                this.$Grid.setHeight(this.$GridParent.getSize().y);
                this.$Grid.resize();
            }
        },

        /**
         * Create elements
         */
        $load: function () {
            var self = this;

            this.$Elm.set('html', Mustache.render(template));

            this.$GridParent = this.$Elm.getElement(
                '.quiqqer-memberships-membershipusersarchive-table'
            );

            this.$Grid = new Grid(this.$GridParent, {
                buttons          : [{
                    name     : 'history',
                    text     : QUILocale.get(lg, 'controls.users.membershipusersarchive.tbl.btn.history'),
                    textimage: 'fa fa-history',
                    events   : {
                        onClick: this.$showHistory
                    }
                }],
                columnModel      : [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.userId'),
                    dataIndex: 'userId',
                    dataType : 'number',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.username'),
                    dataIndex: 'username',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.userFirstname'),
                    dataIndex: 'firstname',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.userLastname'),
                    dataIndex: 'lastname',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.addedDate'),
                    dataIndex: 'addedDate',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.users.membershipusersarchive.tbl.header.archiveDate'),
                    dataIndex: 'archiveDate',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.users.membershipusersarchive.tbl.header.archiveReason'),
                    dataIndex: 'archiveReason',
                    dataType : 'string',
                    width    : 200
                }],
                pagination       : true,
                serverSort       : true,
                selectable       : true,
                multipleSelection: false
            });

            this.$Grid.addEvents({
                onDblClick: self.$showHistory,
                onClick   : function () {
                    var TableButtons = self.$Grid.getAttribute('buttons');
                    var selected     = self.$Grid.getSelectedData().length;

                    if (selected === 1) {
                        TableButtons.history.enable();
                    } else {
                        TableButtons.history.disable();
                    }
                },
                onRefresh : this.$listRefresh
            });

            this.resize();
            this.refresh();
        },

        /**
         * Refresh control data
         */
        refresh: function () {
            this.$Grid.refresh();
        },

        /**
         * Refresh package list
         *
         * @param {Object} Grid
         */
        $listRefresh: function (Grid) {
            if (!this.$Grid) {
                return;
            }

            var self         = this;
            var TableButtons = this.$Grid.getAttribute('buttons');

            TableButtons.history.disable();

            var SearchParams = {
                sortOn : Grid.getAttribute('sortOn'),
                sortBy : Grid.getAttribute('sortBy'),
                perPage: Grid.getAttribute('perPage'),
                page   : Grid.getAttribute('page')
            };

            if (this.$search) {
                SearchParams.search = this.$search;
            }

            this.Loader.show();

            MembershipUsersHandler.getArchiveList(this.$Membership.id, SearchParams).then(function (ResultData) {
                self.Loader.hide();
                self.$setGridData(ResultData);
            });
        },

        /**
         * Set license data to grid
         *
         * @param {Object} GridData
         */
        $setGridData: function (GridData) {
            for (var i = 0, len = GridData.data.length; i < len; i++) {
                var Row = GridData.data[i];

                Row.archiveReason = QUILocale.get(lg,
                    'controls.users.membershipusersarchive.tbl.archiveReason.' + Row.archiveReason
                );
            }

            this.$Grid.setData(GridData);
        },

        /**
         * Show history
         */
        $showHistory: function () {
            var membershipUserId = this.$Grid.getSelectedData()[0].id;

            require([
                'package/quiqqer/memberships/bin/controls/users/MembershipUserHistoryPopup'
            ], function (MembershipUserHistoryPopup) {
                new MembershipUserHistoryPopup({
                    membershipUserId: membershipUserId
                }).open();
            });
        },

        /**
         * Set search term for MembershipUser search
         *
         * @param {String} search
         */
        setSearchTerm: function(search) {
            if (!search || search === '') {
                this.$search = false;
                return;
            }

            this.$search = search;
        }
    });
});
