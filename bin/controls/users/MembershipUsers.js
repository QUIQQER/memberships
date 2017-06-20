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

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/memberships/bin/controls/users/MembershipUsers.html',
    'css!package/quiqqer/memberships/bin/controls/users/MembershipUsers.css'

], function (QUIControl, QUILoader, QUIPopup, QUIConfirm, QUIButton, QUIFormUtils,
             QUIControlUtils, Grid, UserSearchWindow, Memberships, MembershipUsersHandler,
             QUILocale, QUIAjax, Mustache, template) {
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
            '$renew',
            '$removeUser',
            'refresh',
            '$removeUsers',
            '$openUserPanel'
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

            this.$Grid = new Grid(this.$GridParent, {
                buttons          : [{
                    name     : 'adduser',
                    text     : QUILocale.get(lg, 'controls.membershipusers.tbl.btn.adduser'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: this.$addUser
                    }
                }, {
                    name     : 'removeuser',
                    text     : QUILocale.get(lg, 'controls.membershipusers.tbl.btn.removeuser'),
                    textimage: 'fa fa-trash',
                    events   : {
                        onClick: this.$removeUsers
                    }
                }],
                columnModel      : [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
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
                    dataIndex: 'userFirstname',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.userLastname'),
                    dataIndex: 'userLastname',
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
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.renewalCounter'),
                    dataIndex: 'renewalCounter',
                    dataType : 'number',
                    width    : 75
                }, {
                    dataIndex: 'id',
                    dataType : 'number',
                    hidden   : true
                }],
                pagination       : true,
                serverSort       : true,
                selectable       : true,
                multipleSelection: true
            });

            this.$Grid.addEvents({
                onDblClick: self.$openUserPanel,
                onClick   : function () {
                    var TableButtons = self.$Grid.getAttribute('buttons');

                    TableButtons.removeuser.enable();
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
                case 'userFirstname':
                case 'userLastname':
                    return;
            }

            var self         = this;
            var TableButtons = this.$Grid.getAttribute('buttons');

            TableButtons.removeuser.disable();

            var GridParams = {
                sortOn : Grid.getAttribute('sortOn'),
                sortBy : Grid.getAttribute('sortBy'),
                perPage: Grid.getAttribute('perPage'),
                page   : Grid.getAttribute('page')
            };

            this.Loader.show();

            MembershipUsersHandler.getList(this.$Membership.id, GridParams).then(function (ResultData) {
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
            var self = this;

            for (var i = 0, len = GridData.data.length; i < len; i++) {
                var Row = GridData.data[i];

                if (!Row.userFirstname) {
                    Row.userFirstname = '-';
                }

                if (!Row.userLastname) {
                    Row.userLastname = '-';
                }

                //if (Row.active) {
                //    Row.activeStatus = new Element('span', {
                //        'class': 'fa fa-check',
                //        title  : QUILocale.get(lg, 'controls.membershipusers.tbl.status.active'),
                //        alt    : QUILocale.get(lg, 'controls.membershipusers.tbl.status.active')
                //    });
                //} else {
                //    Row.activeStatus = new Element('span', {
                //        'class': 'fa fa-close',
                //        title  : QUILocale.get(lg, 'controls.membershipusers.tbl.status.inactive'),
                //        alt    : QUILocale.get(lg, 'controls.membershipusers.tbl.status.inactive')
                //    });
                //}
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
         * Remove all selected licenses
         */
        $removeUsers: function () {
            var self       = this;
            var deleteData = [];
            var deleteIds  = [];
            var rows       = this.$Grid.getSelectedData();

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
         * Opens a panel for a single location
         */
        $openUserPanel: function () {
            var userId = this.$Grid.getSelectedData()[0].userId;

            require([
                'controls/users/User',
                'utils/Panels'
            ], function (UserPanel, Utils) {
                Utils.openPanelInTasks(new UserPanel(userId));
            });
        },
    });
});
