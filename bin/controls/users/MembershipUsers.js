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
             QUILocale, QUIAjax, Mustache, template, templateEdit) {
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
            '$toggleActiveStatus',
            '$renew',
            '$deleteLicenses',
            '$removeUser',
            'refresh'
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

            this.Loader.inject(this.$Elm);
            this.Loader.show();

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
            var ParentPanelContentElm = this.$Elm.getParent('.qui-panel-content');
            var ElmSize               = this.$Elm.getSize();

            if (ParentPanelContentElm) {
                ElmSize = ParentPanelContentElm.getSize();
            }

            if (this.$Grid && this.$GridParent) {
                var sizeY = ElmSize.y - 45;
                this.$GridParent.set('height', sizeY);

                this.$Grid.setHeight(sizeY);
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
                        onClick: this.$removeUser
                    }
                }],
                columnModel      : [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 50
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.username'),
                    dataIndex: 'username',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.userFirstname'),
                    dataIndex: 'userFirstname',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.userLastName'),
                    dataIndex: 'userLastName',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.addedDate'),
                    dataIndex: 'addedDate',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.beginDate'),
                    dataIndex: 'beginDate',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.endDate'),
                    dataIndex: 'endDate',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipusers.tbl.header.renewalCounter'),
                    dataIndex: 'renewalCounter',
                    dataType : 'number',
                    width    : 75
                }],
                pagination       : true,
                serverSort       : true,
                selectable       : true,
                multipleSelection: true
            });

            this.$Grid.addEvents({
                onDblClick: function () {
                },
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
                //
                //Row.valid = Row.validUntilText;
                //
                //if (Row.isValid) {
                //    Row.valid += ' <span class="quiqqer-memberships-membershipusers-valid">' +
                //        '(' + QUILocale.get(lg, 'controls.membershipusers.license.valid') + ')' +
                //        '</span>';
                //} else {
                //    Row.valid += ' <span class="quiqqer-memberships-membershipusers-invalid">' +
                //        '(' + QUILocale.get(lg, 'controls.membershipusers.license.invalid') + ')' +
                //        '</span>';
                //}
                //
                //Row.created = Row.createdAt + ' (' + Row.createUser + ')';
                //Row.updated = Row.editAt + ' (' + Row.editUser + ')';
                //
                //Row.download = new Element('span', {
                //    'class'  : 'fa fa-download quiqqer-memberships-membershipusers-download',
                //    'data-id': Row.id,
                //    //html     : 'Download',
                //    events   : {
                //        click: function (event) {
                //            self.$downloadLicense(event.target.get('data-id'));
                //        }
                //    }
                //});
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
                        filter_groups_exclude: [self.$Membership.groupIds]
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
                            self.$listRefresh();
                        });
                    }
                }
            }).open();
        },

        /**
         * Edit license data
         */
        $removeUser: function () {
            var self        = this;
            var LicenseData = this.$Grid.getSelectedData()[0];

            this.$Panel.createSheet({
                title : QUILocale.get(lg, 'controls.membershipusers.edit.title'),
                events: {
                    onShow : function (Sheet) {
                        var Content  = Sheet.getContent();
                        var lgPrefix = 'controls.membershipusers.edit.template.';

                        Content.set('html', Mustache.render(templateEdit, {
                            header         : QUILocale.get(lg, lgPrefix + 'header', {
                                title: LicenseData.title,
                                id   : LicenseData.id
                            }),
                            labelTitle     : QUILocale.get(lg, lgPrefix + 'labelTitle'),
                            labelValidUntil: QUILocale.get(lg, lgPrefix + 'labelValidUntil'),
                            title          : LicenseData.title,
                            validUntil     : LicenseData.validUntil ? LicenseData.validUntilText : null
                        }));

                        Content.setStyle('padding', 20);

                        Sheet.addButton(
                            new QUIButton({
                                text     : QUILocale.get('quiqqer/system', 'save'),
                                textimage: 'fa fa-save',
                                events   : {
                                    onClick: function () {
                                        var Form = Content.getElement('form');

                                        self.Loader.show();

                                        LicenseHandler.editLicense(
                                            LicenseData.id,
                                            QUIFormUtils.getFormData(Form)
                                        ).then(function (success) {
                                            self.Loader.hide();

                                            if (!success) {
                                                return;
                                            }

                                            Sheet.destroy();
                                            self.$listRefresh();
                                        });
                                    }
                                }
                            })
                        );
                    },
                    onClose: function (Sheet) {
                        Sheet.destroy();
                    }
                }
            }).show();
        },

        /**
         * Manage packages for a license
         */
        $renew: function () {
            var self        = this;
            var LicenseData = self.$Grid.getSelectedData()[0];
            var BundlePackagesControl;

            // open popup
            var Popup = new QUIPopup({
                icon       : 'fa fa-gift',
                title      : QUILocale.get(
                    lg, 'controls.membershipusers.managebundlepackages.popup.title', {
                        title: LicenseData.title,
                        user : this.$User.getName()
                    }
                ),
                maxHeight  : 800,
                maxWidth   : 450,
                events     : {
                    onOpen: function () {
                        BundlePackagesControl = new LicenseBundles({
                            licenseId: LicenseData.id
                        }).inject(Popup.getContent());
                    }
                },
                closeButton: true
            });

            Popup.open();

            Popup.addButton(new QUIButton({
                text  : QUILocale.get(lg, 'controls.membershipusers.managebundlepackages.popup.btn.text'),
                alt   : QUILocale.get(lg, 'controls.membershipusers.managebundlepackages.popup.btn'),
                title : QUILocale.get(lg, 'controls.membershipusers.managebundlepackages.popup.btn'),
                events: {
                    onClick: function () {
                        Popup.Loader.show();

                        LicenseHandler.editLicense(LicenseData.id, {
                            packageBundleIds: BundlePackagesControl.getBundleIds()
                        }).then(function (LicenseData) {
                            if (!LicenseData) {
                                Popup.Loader.hide();
                                return;
                            }

                            Popup.close();
                            self.$listRefresh();
                        });
                    }
                }
            }));
        },

        /**
         * Remove all selected licenses
         */
        $deleteLicenses: function () {
            var self               = this;
            var deleteLicensesData = [];
            var deleteLicensesIds  = [];
            var rows               = this.$Grid.getSelectedData();

            for (var i = 0, len = rows.length; i < len; i++) {
                deleteLicensesData.push(
                    rows[i].title + ' (ID: #' + rows[i].id + ')'
                );

                deleteLicensesIds.push(rows[i].id);
            }

            // open popup
            var Popup = new QUIConfirm({
                'maxHeight': 300,
                'autoclose': true,

                'information': QUILocale.get(
                    lg,
                    'controls.membershipusers.deletelicenses.popup.info', {
                        licenses: deleteLicensesData.join('<br/>')
                    }
                ),
                'title'      : QUILocale.get(lg, 'controls.membershipusers.deletelicenses.popup.title'),
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

                        LicenseHandler.deleteLicenses(deleteLicensesIds).then(function (success) {
                            if (!success) {
                                Popup.Loader.hide();
                                return;
                            }

                            Popup.close();
                            self.$listRefresh();
                        });
                    }
                }
            });

            Popup.open();
        },

        /**
         * Set active status of a license
         *
         * @param {Integer} licenseId
         */
        $toggleActiveStatus: function () {
            var self            = this;
            var SelectedLicense = self.$Grid.getSelectedData()[0];

            this.Loader.show();

            LicenseHandler.editLicense(SelectedLicense.id, {
                active: SelectedLicense.active ? 0 : 1
            }).then(function (LicenseData) {
                self.Loader.hide();
                self.$listRefresh();
            });
        }
    });
});
