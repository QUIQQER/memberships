/**
 * MembershipsManager JavaScript Control
 *
 * Manages QUIQQER memberships (CRUD)
 *
 * @module package/quiqqer/memberships/bin/controls/MembershipsManager
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/desktop/Panel
 * @require qui/controls/loader/Loader
 * @require qui/controls/windows/Popup
 * @require qui/controls/windows/Confirm
 * @require qui/controls/buttons/Button
 * @require qui/controls/buttons/Separator
 * @require controls/grid/Grid
 * @require utils/Controls
 * @require qui/utils/Form
 * @require package/quiqqer/memberships/bin/PackageMemberships
 * @require package/quiqqer/memberships/bin/controls/MembershipPackages
 * @require Locale
 * @require Ajax
 * @require Mustache
 * @require text!package/quiqqer/memberships/bin/controls/MembershipsManager.html
 * @require text!package/quiqqer/memberships/bin/controls/MembershipsManager.Edit.html
 * @require css!package/quiqqer/memberships/bin/controls/MembershipsManager.css
 */
define('package/quiqqer/memberships/bin/controls/MembershipsManager', [

    'qui/controls/desktop/Panel',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Popup',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Separator',

    'controls/grid/Grid',
    'utils/Controls',
    'qui/utils/Form',

    'package/quiqqer/memberships/bin/PackageMemberships',
    'package/quiqqer/memberships/bin/controls/MembershipPackages',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/memberships/bin/controls/MembershipsManager.html',
    'text!package/quiqqer/memberships/bin/controls/MembershipsManager.Edit.html',
    'css!package/quiqqer/memberships/bin/controls/MembershipsManager.css'

], function (QUIPanel, QUILoader, QUIPopup, QUIConfirm, QUIButton, QUISeparator,
             Grid, QUIControlUtils, QUIFormUtils, MembershipHandler, MembershipPackages,
             QUILocale, QUIAjax, Mustache, template, templateEdit) {
    "use strict";

    var lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/memberships/bin/controls/MembershipsManager',

        Binds: [
            '$onCreate',
            '$onResize',
            '$listRefresh',
            '$onRefresh',
            '$load',
            '$setGridData',
            '$addMembership',
            '$toggleActiveStatus',
            '$managePackages',
            '$deleteMemberships',
            '$editMembership'
        ],

        options: {
            title: QUILocale.get(lg, 'controls.membershipsmanager.title')
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader      = new QUILoader();
            this.$User       = null;
            this.$Grid       = null;
            this.$GridParent = null;
            this.$FormParent = null;
            this.$Panel      = null;

            this.addEvents({
                onCreate : this.$onCreate,
                onRefresh: this.$onRefresh,
                onResize : this.$onResize
            });
        },

        /**
         * Event: onImport
         */
        $onCreate: function () {
            this.Loader.inject(this.$Elm);

            this.addButton({
                name     : 'add',
                text     : QUILocale.get(lg, 'controls.membershipsmanager.tbl.btn.addmembership'),
                textimage: 'fa fa-plus',
                events   : {
                    onClick: this.$addMembership
                }
            });

            this.addButton(new QUISeparator());

            this.addButton({
                name     : 'edit',
                text     : QUILocale.get(lg, 'controls.membershipsmanager.tbl.btn.editmembership'),
                textimage: 'fa fa-edit',
                events   : {
                    onClick: this.$editMembership
                }
            });

            this.addButton(new QUISeparator());

            this.addButton({
                name     : 'delete',
                text     : QUILocale.get(lg, 'controls.membershipsmanager.tbl.btn.removemembership'),
                textimage: 'fa fa-trash',
                events   : {
                    onClick: this.$deleteMemberships
                }
            });

            this.$load();
        },

        /**
         * event: onResize
         */
        $onResize: function () {
            if (this.$GridParent && this.$Grid) {
                var size = this.$GridParent.getSize();

                this.$Grid.setHeight(size.y);
                this.$Grid.resize();
            }
        },

        /**
         * Load memberships management
         */
        $load: function () {
            var self = this;

            this.setContent(Mustache.render(template));
            var Content = this.getContent();

            this.$GridParent = Content.getElement(
                '.quiqqer-memberships-membershipsmanager-table'
            );

            this.$Grid = new Grid(this.$GridParent, {
                columnModel      : [{
                    header   : QUILocale.get('quiqqer/system', 'id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 50
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipsmanager.tbl.header.title'),
                    dataIndex: 'title',
                    dataType : 'string',
                    width    : 250
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipsmanager.tbl.header.description'),
                    dataIndex: 'description',
                    dataType : 'string',
                    width    : 400
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipsmanager.tbl.header.duration'),
                    dataIndex: 'duration',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipsmanager.tbl.header.userCount'),
                    dataIndex: 'userCount',
                    dataType : 'string',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipsmanager.tbl.header.autoRenew'),
                    dataIndex: 'autoRenew',
                    dataType : 'node',
                    width    : 75
                }],
                pagination       : true,
                serverSort       : true,
                selectable       : true,
                multipleSelection: true
            });

            this.$Grid.addEvents({
                onDblClick: function () {
                    self.$managePackages(
                        self.$Grid.getSelectedData()[0].id
                    );
                },
                onClick   : function () {
                    var selected = self.$Grid.getSelectedData().length;

                    self.getButtons('delete').enable();

                    if (selected === 1) {
                        self.getButtons('edit').enable();
                    } else {
                        self.getButtons('edit').disable();
                    }
                },
                onRefresh : this.$listRefresh
            });

            this.resize();
            this.$Grid.refresh();
        },

        /**
         * Event: onRefresh
         */
        $onRefresh: function () {
            if (this.$Grid) {
                this.$Grid.refresh();
            }
        },

        /**
         * Refresh membership list
         *
         * @param {Object} Grid
         */
        $listRefresh: function (Grid) {
            if (!this.$Grid) {
                return;
            }

            var self = this;

            self.getButtons('delete').disable();
            self.getButtons('edit').disable();

            var GridParams = {
                sortOn : Grid.getAttribute('sortOn'),
                sortBy : Grid.getAttribute('sortBy'),
                perPage: Grid.getAttribute('perPage'),
                page   : Grid.getAttribute('page')
            };

            this.Loader.show();

            MembershipHandler.getMemberships(GridParams).then(function (ResultData) {
                self.Loader.hide();
                self.$setGridData(ResultData);
            });
        },

        /**
         * Set memberships data to grid
         *
         * @param {Object} GridData
         */
        $setGridData: function (GridData) {
            var self = this;

            console.log(GridData);

            for (var i = 0, len = GridData.data.length; i < len; i++) {
                var Row = GridData.data[i];

                Row.created = Row.createdAt + ' (' + Row.createUser + ')';
                Row.updated = Row.editAt + ' (' + Row.editUser + ')';
            }

            this.$Grid.setData(GridData);
        },

        /**
         * Add new memberships
         */
        $addMembership: function () {
            var self = this;

            var FuncSubmit = function () {
                var Input = Popup.getContent()
                    .getElement(
                        '.quiqqer-memberships-membershipsmanager-add-input'
                    );

                var title = Input.value.trim();

                if (title === '') {
                    Input.value = '';
                    Input.focus();
                    return;
                }

                Popup.Loader.show();

                MembershipHandler.createMembership(title).then(function (PackageMembershipData) {
                    if (!PackageMembershipData) {
                        Popup.Loader.hide();
                        return;
                    }

                    self.refresh();
                    Popup.close();
                });
            };

            // open popup
            var Popup = new QUIPopup({
                icon       : 'fa fa-plus',
                title      : QUILocale.get(
                    lg, 'controls.membershipsmanager.add.popup.title'
                ),
                maxHeight  : 200,
                maxWidth   : 450,
                events     : {
                    onOpen: function () {
                        var Input = Popup.getContent()
                            .getElement(
                                '.quiqqer-memberships-membershipsmanager-add-input'
                            );

                        Input.addEvents({
                            keyup: function (event) {
                                if (event.code === 13) {
                                    FuncSubmit();
                                    Input.blur();
                                }
                            }
                        });

                        Input.focus();
                    }
                },
                closeButton: true,
                content    : '<label class="quiqqer-memberships-membershipsmanager-add-label">' +
                '<span>' + QUILocale.get(lg, 'controls.membershipsmanager.add.popup.info') + '</span>' +
                '<input type="text" class="quiqqer-memberships-membershipsmanager-add-input"/>' +
                '</label>'
            });

            Popup.open();

            Popup.addButton(new QUIButton({
                text  : QUILocale.get(lg, 'controls.membershipsmanager.add.popup.confirm.btn.text'),
                alt   : QUILocale.get(lg, 'controls.membershipsmanager.add.popup.confirm.btn'),
                title : QUILocale.get(lg, 'controls.membershipsmanager.add.popup.confirm.btn'),
                events: {
                    onClick: FuncSubmit
                }
            }));
        },

        /**
         * Edit membership
         */
        $editMembership: function () {
            var self                  = this;
            var PackageMembershipData = this.$Grid.getSelectedData()[0];

            this.createSheet({
                title : QUILocale.get(lg, 'controls.membershipsmanager.edit.title'),
                events: {
                    onShow : function (Sheet) {
                        var Content = Sheet.getContent();

                        var lgPrefix = 'controls.membershipsmanager.edit.template.';

                        Content.set('html', Mustache.render(templateEdit, {
                            header          : QUILocale.get(lg, lgPrefix + 'header', {
                                title: PackageMembershipData.title,
                                id   : PackageMembershipData.id
                            }),
                            labelTitle      : QUILocale.get(lg, lgPrefix + 'labelTitle'),
                            labelDescription: QUILocale.get(lg, lgPrefix + 'labelDescription'),
                            title           : PackageMembershipData.title,
                            description     : PackageMembershipData.description
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

                                        MembershipHandler.editMembership(
                                            PackageMembershipData.id,
                                            QUIFormUtils.getFormData(Form)
                                        ).then(function () {
                                            self.Loader.hide();
                                            Sheet.destroy();
                                            self.refresh();
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
         * Manage packages for a memberships
         */
        $managePackages: function () {
            var self                  = this;
            var PackageMembershipData = self.$Grid.getSelectedData()[0];
            var MembershipPackagesControl;

            // open popup
            var Popup = new QUIPopup({
                icon       : 'fa fa-gift',
                title      : QUILocale.get(
                    lg, 'controls.membershipsmanager.managemembershippackages.popup.title', {
                        title: PackageMembershipData.title
                    }
                ),
                maxHeight  : 800,
                maxWidth   : 600,
                events     : {
                    onOpen: function () {
                        MembershipPackagesControl = new MembershipPackages({
                            membershipId: PackageMembershipData.id
                        }).inject(Popup.getContent());
                    }
                },
                closeButton: true
            });

            Popup.open();

            Popup.addButton(new QUIButton({
                text  : QUILocale.get(lg, 'controls.membershipsmanager.managemembershippackages.popup.btn.text'),
                alt   : QUILocale.get(lg, 'controls.membershipsmanager.managemembershippackages.popup.btn'),
                title : QUILocale.get(lg, 'controls.membershipsmanager.managemembershippackages.popup.btn'),
                events: {
                    onClick: function () {
                        Popup.Loader.show();

                        MembershipHandler.editMembership(PackageMembershipData.id, {
                            packages: MembershipPackagesControl.getPackageData()
                        }).then(function (PackageMembershipData) {
                            if (!PackageMembershipData) {
                                Popup.Loader.hide();
                                return;
                            }

                            Popup.close();
                            self.refresh();
                        });
                    }
                }
            }));
        },

        /**
         * Remove all selected membershipss
         */
        $deleteMemberships: function () {
            var self                  = this;
            var deleteMembershipsData = [];
            var deleteMembershipsIds  = [];
            var rows                  = this.$Grid.getSelectedData();

            for (var i = 0, len = rows.length; i < len; i++) {
                deleteMembershipsData.push(
                    rows[i].title + ' (ID: #' + rows[i].id + ')'
                );

                deleteMembershipsIds.push(rows[i].id);
            }

            // open popup
            var Popup = new QUIConfirm({
                'maxHeight': 300,
                'autoclose': true,

                'information': QUILocale.get(
                    lg,
                    'controls.membershipsmanager.deletememberships.popup.info', {
                        memberships: deleteMembershipsData.join('<br/>')
                    }
                ),
                'title'      : QUILocale.get(lg, 'controls.membershipsmanager.deletememberships.popup.title'),
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

                        MembershipHandler.deleteMemberships(deleteMembershipsIds).then(function (success) {
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
        }
    });
});
