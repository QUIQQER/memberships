/**
 * MembershipProducts JavaScript Control
 *
 * List all products of a membership
 *
 * @module package/quiqqer/memberships/bin/controls/products/MembershipProducts
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
 * @require text!package/quiqqer/memberships/bin/controls/products/MembershipProducts.html
 * @require css!package/quiqqer/memberships/bin/controls/products/MembershipProducts.css
 */
define('package/quiqqer/memberships/bin/controls/products/MembershipProducts', [

    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/windows/Popup',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Button',

    'qui/utils/Form',
    'utils/Controls',
    'controls/grid/Grid',

    'package/quiqqer/memberships/bin/Memberships',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/memberships/bin/controls/products/MembershipProducts.html',
    'css!package/quiqqer/memberships/bin/controls/products/MembershipProducts.css'

], function (QUIControl, QUILoader, QUIPopup, QUIConfirm, QUIButton, QUIFormUtils,
             QUIControlUtils, Grid, Memberships, QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/memberships/bin/controls/products/MembershipProducts',

        Binds: [
            '$onInject',
            '$onResize',
            '$listRefresh',
            '$setGridData',
            'refresh',
            '$createProduct',
            '$openProductPanel'
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
            this.$Membership = null;
            this.$search     = false;

            this.addEvents({
                onInject: this.$onInject,
                onResize: this.$onResize
            });
        },

        /**
         * Event: onInject
         */
        $onInject: function () {
            var self = this;

            this.$Elm.addClass('quiqqer-memberships-products-membershipproducts');

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

            Memberships.getMembershipView(this.getAttribute('membershipId')).then(function (Membership) {
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
                '.quiqqer-memberships-products-membershipproducts-table'
            );

            this.$Grid = new Grid(this.$GridParent, {
                buttons          : [{
                    name     : 'createproduct',
                    text     : QUILocale.get(lg, 'controls.products.membershipprouducts.tbl.btn.createproduct'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: this.$createProduct
                    }
                }, {
                    name     : 'viewproduct',
                    text     : QUILocale.get(lg, 'controls.products.membershipprouducts.tbl.btn.viewproduct'),
                    textimage: 'fa fa-eye',
                    events   : {
                        onClick: this.$openProductPanel
                    }
                }],
                columnModel      : [{
                    header   : QUILocale.get(lg, 'controls.products.membershipprouducts.tbl.header.id'),
                    dataIndex: 'id',
                    dataType : 'number',
                    width    : 75
                }, {
                    header   : QUILocale.get(lg, 'controls.products.membershipprouducts.tbl.header.articleNo'),
                    dataIndex: 'articleNo',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'controls.products.membershipprouducts.tbl.header.title'),
                    dataIndex: 'title',
                    dataType : 'string',
                    width    : 350
                }],
                pagination       : false,
                serverSort       : false,
                selectable       : true,
                multipleSelection: false
            });

            this.$Grid.addEvents({
                onClick   : function () {
                    var TableButtons = self.$Grid.getAttribute('buttons');
                    var selected     = self.$Grid.getSelectedData().length;

                    if (selected === 1) {
                        TableButtons.viewproduct.enable();
                    } else {
                        TableButtons.viewproduct.disable();
                    }
                },
                onDblClick: self.$openProductPanel,
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

            var TableButtons = this.$Grid.getAttribute('buttons');
            TableButtons.viewproduct.disable();

            var self = this;

            this.Loader.show();

            Memberships.getProducts(this.getAttribute('membershipId')).then(function (products) {
                self.Loader.hide();
                self.$setGridData(products);
            });
        },

        /**
         * Set license data to grid
         *
         * @param {Array} products
         */
        $setGridData: function (products) {
            for (var i = 0, len = products.length; i < len; i++) {
                var Row = products[i];

                if (!Row.articleNo) {
                    Row.articleNo = '-';
                }
            }

            this.$Grid.setData({
                data : products,
                page : 1,
                total: products.length
            });
        },

        /**
         * Create a new product
         */
        $createProduct: function () {
            var self = this;

            // open popup
            var Popup = new QUIConfirm({
                'maxHeight': 300,
                'autoclose': false,

                information: QUILocale.get(lg,
                    'controls.products.membershipprouducts.create.popup.information', {
                        title: this.$Membership.title
                    }
                ),
                title      : QUILocale.get(lg,
                    'controls.products.membershipprouducts.create.popup.title'
                ),
                text       : QUILocale.get(lg,
                    'controls.products.membershipprouducts.create.popup.text', {
                        title: this.$Membership.title
                    }
                ),
                'texticon' : 'fa fa-plus',
                'icon'     : 'fa fa-plus',

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

                        Memberships.createProduct(self.$Membership.id).then(function (success) {
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
         * Open Product panel
         */
        $openProductPanel: function () {
            var productId = this.$Grid.getSelectedData()[0].id;

            require([
                'package/quiqqer/products/bin/controls/products/Product',
                'utils/Panels'
            ], function (ProductPanel, Utils) {
                Utils.openPanelInTasks(new ProductPanel({
                    productId: productId
                }));
            });
        }
    });
});
