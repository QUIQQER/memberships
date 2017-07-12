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
 * @require controls/groups/Select
 * @require controls/grid/Grid
 * @require utils/Controls
 * @require qui/utils/Form
 * @require package/quiqqer/memberships/bin/Memberships
 * @require Locale
 * @require Ajax
 * @require Mustache
 * @require text!package/quiqqer/memberships/bin/controls/MembershipsManager.html
 * @require css!package/quiqqer/memberships/bin/controls/MembershipsManager.css
 *
 * @event onSelect [membershipId, this] - fires if the user double clicks a table entry
 */
define('package/quiqqer/memberships/bin/controls/MembershipsManager', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Popup',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Separator',

    'controls/groups/Select',
    'controls/grid/Grid',
    'utils/Controls',
    'qui/utils/Form',

    'package/quiqqer/memberships/bin/Memberships',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/memberships/bin/controls/MembershipsManager.html',
    'css!package/quiqqer/memberships/bin/controls/MembershipsManager.css'

], function (QUIControl, QUILoader, QUIPopup, QUIConfirm, QUIButton, QUISeparator,
             GroupSelect, Grid, QUIControlUtils, QUIFormUtils, Memberships,
             QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/memberships/bin/controls/MembershipsManager',

        Binds: [
            '$onCreate',
            '$onResize',
            '$listRefresh',
            '$onRefresh',
            '$load',
            '$setGridData',
            '$createMembership',
            '$toggleActiveStatus',
            '$managePackages',
            '$deleteMemberships',
            '$editMembership',
            '$openMembershipPanel'
        ],

        options: {
            multiselect: true, // allow selection of multiple Memberships in table
            showButtons: true // shows buttons for create/edit/delete
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader      = new QUILoader();
            this.$Grid       = null;
            this.$GridParent = null;

            this.addEvents({
                onInject : this.$onInject,
                onRefresh: this.$onRefresh,
                onResize : this.$onResize
            });
        },

        /**
         * Event: onImport
         */
        $onInject: function () {
            this.$Elm.addClass(
                'quiqqer-memberships-membershipsmanager'
            );

            this.Loader.inject(this.$Elm);
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

            this.$Elm.set('html', Mustache.render(template));
            var Content = this.$Elm;

            this.$GridParent = Content.getElement(
                '.quiqqer-memberships-membershipsmanager-table'
            );

            var GridAttributes = {
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
                    dataIndex: 'durationText',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipsmanager.tbl.header.userCount'),
                    dataIndex: 'userCount',
                    dataType : 'string',
                    width    : 75
                }, {
                    header   : QUILocale.get(lg, 'controls.membershipsmanager.tbl.header.autoExtend'),
                    dataIndex: 'autoExtendStatus',
                    dataType : 'node',
                    width    : 100
                }],
                pagination       : true,
                serverSort       : true,
                selectable       : true,
                multipleSelection: this.getAttribute('multiselect')
            };

            if (this.getAttribute('showButtons')) {
                GridAttributes.buttons = [{
                    name     : 'add',
                    text     : QUILocale.get(lg, 'controls.membershipsmanager.tbl.btn.addmembership'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: this.$createMembership
                    }
                }, {
                    name     : 'edit',
                    text     : QUILocale.get(lg, 'controls.membershipsmanager.tbl.btn.editmembership'),
                    textimage: 'fa fa-edit',
                    events   : {
                        onClick: this.$openMembershipPanel
                    }
                }, {
                    name     : 'delete',
                    text     : QUILocale.get(lg, 'controls.membershipsmanager.tbl.btn.removemembership'),
                    textimage: 'fa fa-trash',
                    events   : {
                        onClick: this.$deleteMemberships
                    }
                }];
            }

            this.$Grid = new Grid(this.$GridParent, GridAttributes);

            this.$Grid.addEvents({
                onDblClick: function () {
                    self.fireEvent('select', [self.$Grid.getSelectedData()[0].id, self]);
                },
                onClick   : function () {
                    var selected     = self.$Grid.getSelectedData().length;
                    var TableButtons = self.$Grid.getAttribute('buttons');

                    if (!Object.getLength(TableButtons)) {
                        return;
                    }

                    TableButtons.delete.enable();

                    if (selected === 1) {
                        TableButtons.edit.enable();
                    } else {
                        TableButtons.edit.disable();
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

            var self         = this;
            var TableButtons = self.$Grid.getAttribute('buttons');

            if (Object.getLength(TableButtons)) {
                TableButtons.delete.disable();
                TableButtons.edit.disable();
            }

            var GridParams = {
                sortOn : Grid.getAttribute('sortOn'),
                sortBy : Grid.getAttribute('sortBy'),
                perPage: Grid.getAttribute('perPage'),
                page   : Grid.getAttribute('page')
            };

            this.Loader.show();

            Memberships.getList(GridParams).then(function (ResultData) {
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
            for (var i = 0, len = GridData.data.length; i < len; i++) {
                var Row = GridData.data[i];

                if (Row.autoExtend) {
                    Row.autoExtendStatus = new Element('span', {
                        'class': 'fa fa-check'
                    });
                } else {
                    Row.autoExtendStatus = new Element('span', {
                        'class': 'fa fa-close'
                    });
                }

                if (Row.duration) {
                    var duration = Row.duration.split('-');

                    Row.durationText = duration[0]
                        + ' '
                        + QUILocale.get(
                            lg,
                            'controls.inputduration.period.'
                            + duration[1]
                        );
                }
            }

            this.$Grid.setData(GridData);
        },

        /**
         * Create new memberships
         */
        $createMembership: function () {
            var self = this;
            var GroupSelectControl, Input;

            var FuncSubmit = function () {
                var title = Input.value.trim();

                if (title === '') {
                    Input.value = '';
                    Input.focus();
                    return;
                }

                var groupIds = GroupSelectControl.getValue();

                if (groupIds === '') {
                    GroupSelectControl.focus();
                    return;
                }

                groupIds = groupIds.split(',');

                Popup.Loader.show();

                Memberships.createMembership(title, groupIds).then(function (MembershipData) {
                    if (!MembershipData) {
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
                maxHeight  : 375,
                maxWidth   : 450,
                events     : {
                    onOpen: function () {
                        var Content = Popup.getContent();

                        Input = Content.getElement(
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

                        var GroupsElm = Content.getElement(
                            '.quiqqer-memberships-membershipsmanager-add-groups'
                        );

                        GroupSelectControl = new GroupSelect().inject(
                            GroupsElm
                        );
                    }
                },
                closeButton: true,
                content    : '<label class="quiqqer-memberships-membershipsmanager-add-label">' +
                '<span>' + QUILocale.get(lg, 'controls.membershipsmanager.add.popup.title.info') + '</span>' +
                '<input type="text" class="quiqqer-memberships-membershipsmanager-add-input"/>' +
                '</label>' +
                '<div class="quiqqer-memberships-membershipsmanager-add-groups">' +
                '<span>' + QUILocale.get(lg, 'controls.membershipsmanager.add.popup.groups.info') + '</span>' +
                '</div>'
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

                                        Memberships.editMembership(
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
                'title'      : QUILocale.get(
                    lg,
                    'controls.membershipsmanager.deletememberships.popup.title'
                ),
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

                        Memberships.deleteMemberships(deleteMembershipsIds).then(function (success) {
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
         * Get IDs of selected Memberships
         *
         * @return {Array}
         */
        getSelectedIds: function () {
            var ids      = [];
            var selected = this.$Grid.getSelectedData();

            for (var i = 0, len = selected.length; i < len; i++) {
                ids.push(selected[0].id);
            }

            return ids;
        },

        /**
         * Opens a panel for a single membership
         */
        $openMembershipPanel: function () {
            var membershipId = this.$Grid.getSelectedData()[0].id;

            require([
                'package/quiqqer/memberships/bin/controls/Membership',
                'utils/Panels'
            ], function (MembershipPanel, Utils) {
                Utils.openPanelInTasks(new MembershipPanel({
                    id   : membershipId,
                    '#id': 'quiqqer_memberships_' + membershipId
                }));
            });
        }
    });
});
