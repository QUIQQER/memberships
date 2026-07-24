/**
 * MembershipUsers JavaScript Control
 *
 * Manages QUIQQER licenses for a single user (customer)
 */
define('package/quiqqer/memberships/bin/controls/users/MembershipUsers', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Popup',
    'qui/controls/windows/Confirm',

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

], function (QUIControl, QUILoader, QUIPopup, QUIConfirm, QUIFormUtils,
             QUIControlUtils, Grid, UserSearchWindow, Memberships, MembershipUsersHandler,
             MembershipUserEdit, QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    const lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/memberships/bin/controls/users/MembershipUsers',

        Binds: [
            '$onInject',
            '$onResize',
            '$listRefresh',
            '$setGridData',
            '$addUser',
            '$extend',
            '$removeUser',
            '$onDblClick',
            'refresh',
            '$removeUsers',
            '$showHistory',
            '$editUser',
            '$openUser',
            '$openContract'
        ],

        options: {
            membershipId      : false,
            contractsInstalled: false
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader = new QUILoader();
            this.$User = null;
            this.$Grid = null;
            this.$GridParent = null;
            this.$Membership = null;
            this.$search = false;
            this.$EditActionItem = null;
            this.$RemoveActionItem = null;

            this.addEvents({
                onInject: this.$onInject,
                onResize: this.$onResize
            });
        },

        /**
         * Event: onImport
         */
        $onInject: function () {
            this.$Elm.addClass('quiqqer-memberships-membershipusers');

            this.Loader.inject(this.$Elm);
            this.Loader.show();

            // if control is injected in a panel, register onResize event
            QUIControlUtils.getControlByElement(
                this.$Elm.getParent('.qui-panel')
            ).then((Panel) => {
                Panel.addEvent('onResize', this.$onResize);
            }, function () {
                // do nothing if no panel found
            });

            Memberships.getMembership(this.getAttribute('membershipId')).then((Membership) => {
                this.Loader.hide();
                this.$Membership = Membership;
                this.$load();
            });
        },

        /**
         * event: onResize
         */
        $onResize: function () {
            if (this.$Grid && this.$GridParent) {
                this.$Grid.setHeight(this.$GridParent.clientHeight);
                this.$Grid.resize();
            }
        },

        /**
         * Load license management
         */
        $load: function () {
            this.$Elm.innerHTML = Mustache.render(template);

            this.$GridParent = this.$Elm.querySelector(
                '.quiqqer-memberships-membershipusers-table'
            );

            this.$Grid = new Grid(this.$GridParent, {
                buttons: [{
                    name: 'adduser',
                    text: QUILocale.get(lg, 'controls.membershipusers.tbl.btn.adduser'),
                    textimage: 'fa fa-plus',
                    events: {
                        onClick: this.$addUser
                    }
                }, {
                    name: 'edit',
                    text: QUILocale.get(lg, 'controls.membershipusers.tbl.btn.edit'),
                    textimage: 'fa fa-edit',
                    disabled: true,
                    events: {
                        onClick: this.$editUser
                    }
                }, {
                    name: 'removeuser',
                    text: QUILocale.get(lg, 'controls.membershipusers.tbl.btn.removeuser'),
                    textimage: 'fa fa-trash',
                    disabled: true,
                    events: {
                        onClick: this.$removeUsers
                    }
                }, {
                    name: 'history',
                    text: QUILocale.get(lg, 'controls.users.membershipusersarchive.tbl.btn.history'),
                    textimage: 'fa fa-history',
                    disabled: true,
                    position: 'right',
                    events: {
                        onClick: this.$showHistory
                    }
                }],
                columnModel: [{
                    header: QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType: 'number',
                    width: 100
                }, {
                    header: QUILocale.get(lg, 'controls.membershipusers.tbl.header.userId'),
                    dataIndex: 'userId',
                    dataType: 'number',
                    width: 100,
                    className: 'clickable'
                }, {
                    header: QUILocale.get(lg, 'controls.membershipusers.tbl.header.contractId'),
                    dataIndex: 'contractId',
                    dataType: 'string',
                    width: 100,
                    className: 'clickable'
                }, {
                    header: QUILocale.get(lg, 'controls.membershipusers.tbl.header.username'),
                    dataIndex: 'username',
                    dataType: 'string',
                    width: 150,
                    className: 'clickable'
                }, {
                    header: QUILocale.get(lg, 'controls.membershipusers.tbl.header.userFirstname'),
                    dataIndex: 'firstname',
                    dataType: 'string',
                    width: 100
                }, {
                    header: QUILocale.get(lg, 'controls.membershipusers.tbl.header.userLastname'),
                    dataIndex: 'lastname',
                    dataType: 'string',
                    width: 100
                }, {
                    header: QUILocale.get(lg, 'controls.membershipusers.tbl.header.addedDate'),
                    dataIndex: 'addedDate',
                    dataType: 'string',
                    width: 150
                }, {
                    header: QUILocale.get(lg, 'controls.membershipusers.tbl.header.beginDate'),
                    dataIndex: 'beginDate',
                    dataType: 'string',
                    width: 150
                }, {
                    header: QUILocale.get(lg, 'controls.membershipusers.tbl.header.endDate'),
                    dataIndex: 'endDate',
                    dataType: 'string',
                    width: 150
                }, {
                    header: QUILocale.get(lg, 'controls.membershipusers.tbl.header.status'),
                    dataIndex: 'status',
                    dataType: 'node',
                    width: 75
                }, {
                    header: QUILocale.get(lg, 'controls.membershipusers.tbl.header.extendCounter'),
                    dataIndex: 'extendCounter',
                    dataType: 'number',
                    width: 120
                }],
                pagination: true,
                serverSort: true,
                selectable: true,
                multipleSelection: true
            });

            this.$EditActionItem = this.$Grid.getButton('edit');
            this.$RemoveActionItem = this.$Grid.getButton('removeuser');

            this.$Grid.addEvents({
                onDblClick: this.$onDblClick,
                onClick: () => {
                    const TableButtons = this.$Grid.getAttribute('buttons');
                    const selected = this.$Grid.getSelectedData().length;

                    if (this.$RemoveActionItem && selected > 0) {
                        this.$RemoveActionItem.enable();
                    } else if (this.$RemoveActionItem) {
                        this.$RemoveActionItem.disable();
                    }

                    if (selected === 1) {
                        TableButtons.history.enable();
                        if (this.$EditActionItem) {
                            this.$EditActionItem.enable();
                        }
                    } else {
                        TableButtons.history.disable();
                        if (this.$EditActionItem) {
                            this.$EditActionItem.disable();
                        }
                    }
                },
                onRefresh: this.$listRefresh
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

            const TableButtons = this.$Grid.getAttribute('buttons');

            TableButtons.history.disable();

            if (this.$EditActionItem) {
                this.$EditActionItem.disable();
            }

            if (this.$RemoveActionItem) {
                this.$RemoveActionItem.disable();
            }

            const SearchParams = {
                sortOn: Grid.getAttribute('sortOn'),
                sortBy: Grid.getAttribute('sortBy'),
                perPage: Grid.getAttribute('perPage'),
                page: Grid.getAttribute('page')
            };

            switch (SearchParams.sortOn) {
                case 'status':
                    SearchParams.sortOn = 'cancelled';
                    break;
            }

            if (this.$search) {
                SearchParams.search = this.$search;
            }

            this.Loader.show();

            MembershipUsersHandler.getList(this.$Membership.id, SearchParams).then((ResultData) => {
                this.Loader.hide();
                this.$Grid.hideLoader();
                this.$setGridData(ResultData);
            });
        },

        /**
         * Set license data to grid
         *
         * @param {Object} GridData
         */
        $setGridData: function (GridData) {
            for (let i = 0, len = GridData.data.length; i < len; i++) {
                const Row = GridData.data[i];

                if (!Row.contractId) {
                    Row.contractId = '---';
                }

                if (Row.cancelled) {
                    Row.status = document.createElement('span');
                    Row.status.className = 'quiqqer-memberships-membershipusers-table-status' +
                        ' quiqqer-memberships-membershipusers-table-status-warning';
                    Row.status.innerHTML = QUILocale.get(lg, 'controls.membershipusers.tbl.status.cancelled');
                } else {
                    Row.status = document.createElement('span');
                    Row.status.className = 'quiqqer-memberships-membershipusers-table-status' +
                        ' quiqqer-memberships-membershipusers-table-status-ok';
                    Row.status.innerHTML = QUILocale.get(lg, 'controls.membershipusers.tbl.status.active');
                }
            }

            this.$Grid.setData(GridData);
        },

        /**
         * Handle a double click on a grid cell
         *
         * @param {Object} data
         * @return {Promise|void}
         */
        $onDblClick: function (data) {
            if (typeof data === 'undefined' ||
                typeof data.cell === 'undefined') {
                this.$editUser();
                return;
            }

            const rowData = this.$Grid.getDataByRow(data.row);
            const dataIndex = data.cell.get('data-index');
            const items = [{
                icon: 'fa fa-edit',
                text: QUILocale.get(lg, 'controls.membershipusers.tbl.btn.edit'),
                events: {
                    onClick: () => {
                        this.$editUser();
                    }
                }
            }];
            let title;

            switch (dataIndex) {
                case 'userId':
                case 'username':
                    if (!rowData.userId) {
                        this.$editUser();
                        return;
                    }

                    title = rowData.userId;
                    items.push({
                        icon: 'fa fa-user-o',
                        text: QUILocale.get(
                            lg,
                            'controls.membershipusers.tbl.btn.openUser'
                        ),
                        events: {
                            onClick: () => {
                                this.$openUser(rowData.userId);
                            }
                        }
                    });
                    break;

                case 'contractId':
                    if (!this.getAttribute('contractsInstalled') ||
                        !rowData.contractId ||
                        rowData.contractId === '---') {
                        this.$editUser();
                        return;
                    }

                    title = rowData.contractId;
                    items.push({
                        icon: 'fa fa-file-text',
                        text: QUILocale.get(
                            lg,
                            'controls.membershipusers.tbl.btn.openContract'
                        ),
                        events: {
                            onClick: () => {
                                this.$openContract(rowData.contractId);
                            }
                        }
                    });
                    break;

                default:
                    this.$editUser();
                    return;
            }

            const position = data.cell.getPosition();

            return new Promise(function (resolve) {
                require([
                    'qui/controls/contextmenu/Menu',
                    'qui/controls/contextmenu/Item'
                ], function (QUIMenu, QUIMenuItem) {
                    const Menu = new QUIMenu({
                        events: {
                            onBlur: function () {
                                Menu.hide();
                                Menu.destroy();
                            }
                        }
                    });

                    for (let i = 0, len = items.length; i < len; i++) {
                        Menu.appendChild(new QUIMenuItem(items[i]));
                    }

                    Menu.inject(document.body);
                    Menu.setPosition(position.x, position.y + data.cell.getSize().y);
                    Menu.setTitle(title);
                    Menu.show();
                    Menu.focus();

                    resolve();
                });
            });
        },

        /**
         * Add new license
         */
        $addUser: function () {
            const AddUsersWindow = new UserSearchWindow({
                search: true,
                searchSettings: {
                    filter: {
                        filter_groups_exclude: this.$Membership.uniqueGroupIds
                    }
                },
                events: {
                    onSubmit: (Control, users) => {
                        const userIds = [];

                        for (let i = 0, len = users.length; i < len; i++) {
                            userIds.push(users[i].id);
                        }

                        this.Loader.show();

                        MembershipUsersHandler.addMembershipUsers(
                            this.$Membership.id,
                            userIds
                        ).then((success) => {
                            this.Loader.hide();

                            if (!success) {
                                return;
                            }

                            AddUsersWindow.close();
                            this.refresh();
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
            const data = this.$Grid.getSelectedData();
            let EditControl;

            if (!data.length) {
                return;
            }

            const membershipUserId = data[0].id;

            const Popup = new QUIConfirm({
                'class': 'quiqqer-memberships-membershipuseredit-confirm',
                'maxHeight': 600,
                maxWidth: 460,
                'autoclose': false,
                'title': QUILocale.get(lg, 'controls.membershipusers.edit.popup.title'),
                'text': QUILocale.get(lg, 'controls.users.membershipuseredit.template.header', {
                    id: data[0].id,
                    name: data[0].username
                }),
                'information': QUILocale.get(
                    lg,
                    'controls.users.membershipuseredit.template.info'
                ),
                'texticon': false,
                'icon': 'fa fa-edit',
                cancel_button: {
                    text: QUILocale.get(lg, 'controls.membershipusers.delete.popup.cancel.btn'),
                    textimage: 'fa fa-times'
                },
                ok_button: {
                    text: QUILocale.get(lg, 'controls.users.membershipuseredit.btn.save'),
                    textimage: 'fa fa-save'
                },
                events: {
                    onOpen: () => {
                        EditControl = new MembershipUserEdit({
                            showButtons: false,
                            membershipUserId: membershipUserId,
                            events: {
                                onSubmit: () => {
                                    Popup.close();
                                    this.refresh();
                                }
                            }
                        }).inject(
                            Popup.getContent()
                        );
                    },
                    onSubmit: () => {
                        if (!EditControl) {
                            return;
                        }

                        EditControl.submit();
                    }
                }
            });

            Popup.open();
        },

        /**
         * Remove all selected licenses
         */
        $removeUsers: function () {
            const deleteData = [];
            const deleteIds = [];
            const rows = this.$Grid.getSelectedData();
            let Popup;

            if (!rows.length) {
                return;
            }

            for (let i = 0, len = rows.length; i < len; i++) {
                deleteData.push(
                    rows[i].username + ' (ID: #' + rows[i].id + ')'
                );

                deleteIds.push(rows[i].id);
            }

            Popup = new QUIConfirm({
                maxHeight: 320,
                maxWidth: 640,
                autoclose: true,
                text: QUILocale.get(lg, 'controls.membershipusers.delete.popup.text'),
                information: QUILocale.get(
                    lg,
                    'controls.membershipusers.delete.popup.info', {
                        users: deleteData.join('<br/>')
                    }
                ),
                title: QUILocale.get(lg, 'controls.membershipusers.delete.popup.title'),
                texticon: 'fa fa-trash',
                icon: 'fa fa-trash',
                cancel_button: {
                    text: QUILocale.get(lg, 'controls.membershipusers.delete.popup.cancel.btn'),
                    textimage: 'fa fa-times'
                },
                ok_button: {
                    text: QUILocale.get(lg, 'controls.membershipusers.delete.popup.remove.btn'),
                    textimage: 'fa fa-trash'
                },
                events: {
                    onSubmit: () => {
                        Popup.close();
                        this.$Grid.showLoader();

                        MembershipUsersHandler.deleteMembershipUsers(deleteIds).then((success) => {
                            if (!success) {
                                this.$Grid.hideLoader();
                                return;
                            }

                            this.refresh();
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
            const data = this.$Grid.getSelectedData();

            if (!data.length) {
                return;
            }

            const membershipUserId = data[0].id;

            require([
                'package/quiqqer/memberships/bin/controls/users/MembershipUserHistoryPopup'
            ], function (MembershipUserHistoryPopup) {
                new MembershipUserHistoryPopup({
                    membershipUserId: membershipUserId
                }).open();
            });
        },

        /**
         * Open the linked user
         *
         * @param {Number|String} userId
         */
        $openUser: function (userId) {
            if (!userId) {
                return;
            }

            require([
                'package/quiqqer/customer/bin/backend/Handler'
            ], function (CustomerHandler) {
                CustomerHandler.openCustomer(userId);
            });
        },

        /**
         * Open the linked contract
         *
         * @param {Number|String} contractId
         */
        $openContract: function (contractId) {
            if (!contractId) {
                return;
            }

            require([
                'package/quiqqer/contracts/bin/backend/controls/panels/Contract',
                'utils/Panels'
            ], function (Contract, PanelUtils) {
                const Panel = new Contract({
                    contractId: contractId,
                    '#id': contractId
                });

                PanelUtils.openPanelInTasks(Panel);
            });
        },

        /**
         * Set search term for MembershipUser search
         *
         * @param {String} search
         */
        setSearchTerm: function (search) {
            if (!search || search === '') {
                this.$search = false;
                return;
            }

            this.$search = search;
        }
    });
});
