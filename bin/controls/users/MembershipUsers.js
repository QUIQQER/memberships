/**
 * MembershipUsers JavaScript Control
 *
 * Manages QUIQQER licenses for a single user (customer)
 *
 * @module package/quiqqer/memberships/bin/controls/users/MembershipUsers
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
 * @require text!package/quiqqer/memberships/bin/controls/users/MembershipUsers.html
 * @require css!package/quiqqer/memberships/bin/controls/users/MembershipUsers.css
 */
define('package/quiqqer/memberships/bin/controls/users/MembershipUsers', [

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
    'package/quiqqer/memberships/bin/controls/users/MembershipUserEdit',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/memberships/bin/controls/users/MembershipUsers.html',
    'css!package/quiqqer/memberships/bin/controls/users/MembershipUsers.css'

], function (QUIControl, QUILoader, QUIPopup, QUIConfirm, QUIButton, QUIFormUtils,
             QUIControlUtils, Grid, UserSearchWindow, Memberships, MembershipUsersHandler,
             MembershipUserEdit, QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/memberships/bin/controls/users/MembershipUsers',

        Binds: [
            '$onInject',
            '$onResize',
            '$listRefresh',
            '$setGridData',
            '$addUser',
            '$extend',
            '$removeUser',
            'refresh',
            '$removeUsers',
            '$showHistory',
            '$editUser'
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
            this.$search     = false;

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

            this.$Elm.addClass('quiqqer-memberships-membershipusers');

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
         * Load license management
         */
        $load: function () {
            var self = this;

            this.$Elm.set('html', Mustache.render(template));

            this.$GridParent = this.$Elm.getElement(
                '.quiqqer-memberships-membershipusers-table'
            );

            var ActionBtn = new QUIButton({
                text  : QUILocale.get(lg, 'controls.membershipusers.tbl.btn.actions'),
                name  : 'actions',
                styles: {
                    float: 'right'
                }
            });

            ActionBtn.appendChild({
                name  : 'adduser',
                text  : QUILocale.get(lg, 'controls.membershipusers.tbl.btn.adduser'),
                icon  : 'fa fa-plus',
                events: {
                    onClick: this.$addUser
                }
            }).appendChild({
                name  : 'edit',
                text  : QUILocale.get(lg, 'controls.membershipusers.tbl.btn.edit'),
                icon  : 'fa fa-edit',
                events: {
                    onClick: this.$editUser
                }
            }).appendChild({
                name  : 'removeuser',
                text  : QUILocale.get(lg, 'controls.membershipusers.tbl.btn.removeuser'),
                icon  : 'fa fa-trash',
                events: {
                    onClick: this.$removeUsers
                }
            });

            this.$Grid = new Grid(this.$GridParent, {
                buttons          : [{
                    name     : 'history',
                    text     : QUILocale.get(lg, 'controls.users.membershipusersarchive.tbl.btn.history'),
                    textimage: 'fa fa-history',
                    events   : {
                        onClick: this.$showHistory
                    }
                }, ActionBtn],
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
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.userFullName'),
                    dataIndex: 'userFullName',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.addedDate'),
                    dataIndex: 'addedDate',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.beginDate'),
                    dataIndex: 'beginDate',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.endDate'),
                    dataIndex: 'endDate',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.status'),
                    dataIndex: 'status',
                    dataType : 'node',
                    width    : 75
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.extendCounter'),
                    dataIndex: 'extendCounter',
                    dataType : 'number',
                    width    : 120
                }],
                pagination       : true,
                serverSort       : true,
                selectable       : true,
                multipleSelection: true
            });

            this.$Grid.addEvents({
                onDblClick: self.$editUser,
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

            switch (Grid.getAttribute('sortOn')) {
                // cannot sort on certain columns
                case 'username':
                case 'userFullName':
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

            MembershipUsersHandler.getList(this.$Membership.id, SearchParams).then(function (ResultData) {
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

                if (!Row.userFullName || Row.userFullName === Row.username) {
                    Row.userFullName = '-';
                }

                if (Row.cancelled) {
                    Row.status = new Element('span', {
                        'class': 'quiqqer-memberships-membershipusers-table-status' +
                        ' quiqqer-memberships-membershipusers-table-status-warning',
                        html   : QUILocale.get(lg, 'controls.membershipusers.tbl.status.cancelled')
                    });
                } else {
                    Row.status = new Element('span', {
                        'class': 'quiqqer-memberships-membershipusers-table-status' +
                        ' quiqqer-memberships-membershipusers-table-status-ok',
                        html   : QUILocale.get(lg, 'controls.membershipusers.tbl.status.active')
                    });
                }
            }

            this.$Grid.setData(GridData);
        },

        /**
         * Add new license
         */
        $addUser: function () {
            var self = this;

            var AddUsersWindow = new UserSearchWindow({
                search        : true,
                searchSettings: {
                    filter: {
                        filter_groups_exclude: self.$Membership.uniqueGroupIds
                    }
                },
                events        : {
                    onSubmit: function (Control, users) {
                        var userIds = [];

                        for (var i = 0, len = users.length; i < len; i++) {
                            userIds.push(users[i].id);
                        }

                        self.Loader.show();

                        MembershipUsersHandler.addMembershipUsers(
                            self.$Membership.id,
                            userIds
                        ).then(function (success) {
                            self.Loader.hide();

                            if (!success) {
                                return;
                            }

                            AddUsersWindow.close();
                            self.refresh();
                        });
                    }
                }
            });

            AddUsersWindow.open();
        },

        /**
         * Edit MembershipUser
         */
        $editUser: function () {
            var self = this;
            var data = this.$Grid.getSelectedData();

            if (!data.length) {
                return;
            }

            var membershipUserId = data[0].id;

            // open popup
            var Popup = new QUIPopup({
                'maxHeight': 325,
                maxWidth   : 500,
                'autoclose': true,
                'title'    : QUILocale.get(lg, 'controls.membershipusers.edit.popup.title'),
                'texticon' : 'fa fa-edit',
                'icon'     : 'fa fa-edit',

                buttons: false,
                events : {
                    onOpen: function () {
                        new MembershipUserEdit({
                            membershipUserId: membershipUserId,
                            events          : {
                                onSubmit: function () {
                                    Popup.close();
                                    self.refresh();
                                }
                            }
                        }).inject(
                            Popup.getContent()
                        );
                    }
                }
            });

            Popup.open();
        },

        /**
         * Remove all selected licenses
         */
        $removeUsers: function () {
            var self       = this;
            var deleteData = [];
            var deleteIds  = [];
            var rows       = this.$Grid.getSelectedData();

            if (!rows.length) {
                return;
            }

            for (var i = 0, len = rows.length; i < len; i++) {
                deleteData.push(
                    rows[i].username + ' (ID: #' + rows[i].id + ')'
                );

                deleteIds.push(rows[i].id);
            }

            // open popup
            var Popup = new QUIConfirm({
                'maxHeight': 300,
                'autoclose': true,

                'information': QUILocale.get(
                    lg,
                    'controls.membershipusers.delete.popup.info', {
                        users: deleteData.join('<br/>')
                    }
                ),
                'title'      : QUILocale.get(lg, 'controls.membershipusers.delete.popup.title'),
                'texticon'   : 'fa fa-trash',
                'icon'       : 'fa fa-trash',

                cancel_button: {
                    text     : false,
                    textimage: 'icon-remove fa fa-remove'
                },
                ok_button    : {
                    text     : false,
                    textimage: 'icon-ok fa fa-check'
                },
                events       : {
                    onSubmit: function () {
                        Popup.Loader.show();

                        MembershipUsersHandler.deleteMembershipUsers(deleteIds).then(function (success) {
                            if (!success) {
                                Popup.Loader.hide();
                                return;
                            }

                            Popup.close();
                            self.refresh();
                        });
                    }
                }
            });

            Popup.open();
        },

        /**
         * Show history
         */
        $showHistory: function () {
            var data = this.$Grid.getSelectedData();

            if (!data.length) {
                return;
            }

            var membershipUserId = data[0].id;

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
