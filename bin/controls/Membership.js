/**
 * Display and edit data of a single membership
 *
 * @author www.pcsg.de (Henning Leutz)
 * @authro www.pcsg.de (Patrick Müller)
 * @module package/quiqqer/memberships/bin/controls/Membership
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 * @require qui/controls/buttons/Button
 * @require qui/controls/buttons/Seperator
 * @require qui/utils/Object
 * @require Ajax
 * @require Locale
 * @require utils/Controls
 * @require css!package/quiqqer/memberships/bin/controls/Membership.css
 * @require css!controls/desktop/panels/XML.css
 *
 * @event onSave [this] - If the user saves the membership data
 */
define('package/quiqqer/memberships/bin/controls/Membership', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/ButtonSwitch',
    'qui/controls/buttons/Seperator',
    'qui/utils/Object',
    'qui/utils/Form',

    'Ajax',
    'Locale',
    'Mustache',

    'utils/Controls',
    'utils/Lock',

    'package/quiqqer/memberships/bin/Memberships',

    'text!package/quiqqer/memberships/bin/controls/Membership.Settings.html',
    //'css!package/quiqqer/memberships/bin/controls/Membership.css',
    'css!controls/desktop/panels/XML.css'

], function (QUI, QUIPanel, QUIButton, QUIButtonSwitch, QUISeperator,
             QUIObjectUtils, QUIFormUtils, QUIAjax, QUILocale, Mustache, ControlUtils,
             QUILocker, Memberships, templateSettings) {
    "use strict";

    var lg = 'quiqqer/memberships';

    /**
     * @class package/quiqqer/memberships/bin/controls/Membership
     *
     * @param {number} membershipId - ID of Membership
     * @param {string} [icon] - Panel icon
     */
    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/memberships/bin/controls/Membership',

        Binds: [
            '$onCreate',
            '$onDestroy',
            '$onCategoryActive',
            '$loadSettings',
            'loadCategory',
            'unloadCategory',
            'save',
            '$checkLock',
            '$showLockInfo',
            '$onActivationStatusChange'
        ],

        options: {
            id  : false,
            icon: 'fa fa-id-card'
        },

        initialize: function (options) {
            this.parent(options);

            this.$Control     = null;
            this.$Membership  = null;
            this.$lockKey     = false;
            this.$initialLoad = true;
            this.$isLocked    = true;
            this.$refreshMode = false;

            this.addEvents({
                onCreate : this.$onCreate,
                onDestroy: this.$onDestroy
            });
        },

        /**
         * Internal creation
         */
        $onCreate: function () {
            var self = this;

            this.getCategoryBar().clear();
            this.getButtonBar().clear();

            // load buttons
            this.addButton({
                name     : 'save',
                text     : QUILocale.get('quiqqer/system', 'desktop.panels.xml.btn.save'),
                textimage: 'fa fa-save',
                events   : {
                    onClick: self.save
                }
            });

            this.addButton({
                name     : 'reload',
                text     : QUILocale.get('quiqqer/system', 'desktop.panels.xml.btn.cancel'),
                textimage: 'fa fa-ban',
                events   : {
                    onClick: function () {
                        self.$refreshMode = true;
                        self.$onCreate();
                    }
                }
            });

            this.addCategory(new QUIButton({
                icon  : 'fa fa-gears',
                text  : QUILocale.get(lg, 'controls.membership.category.settings'),
                events: {
                    onActive: function () {
                        self.unloadCategory();
                        self.$loadSettings();
                    }
                }
            }));

            this.Loader.show();

            Promise.all([
                Memberships.getInstalledMembershipPackages(),
                Memberships.getMembership(this.getAttribute('id'))
            ]).then(function (result) {
                var installedPackages = result[0];
                self.$Membership      = result[1];

                // set title
                var Titles = JSON.decode(self.$Membership.title);

                if (Titles) {
                    for (var lang in Titles) {
                        if (!Titles.hasOwnProperty(lang)) {
                            continue;
                        }

                        self.setAttribute(
                            'title',
                            QUILocale.get(lg, 'controls.membership.title', {
                                title: Titles[lang]
                            })
                        );

                        break;
                    }
                }

                if (installedPackages.contains('quiqqer/products')) {
                    self.addCategory(new QUIButton({
                        textimage: 'fa fa-money',
                        text     : QUILocale.get(lg, 'controls.membership.category.products'),
                        events   : {
                            onActive: self.$loadProducts
                        }
                    }));
                }

                if (installedPackages.contains('quiqqer/contracts')) {
                    self.addCategory(new QUIButton({
                        textimage: 'fa fa-handshake-o',
                        text     : QUILocale.get(lg, 'controls.membership.category.contracts'),
                        events   : {
                            onActive: self.$loadContracts
                        }
                    }));
                }

                self.$loadSettings();
            });
        },

        /**
         * Panel event: onDestroy
         */
        $onDestroy: function () {
            if (this.$isLocked) {
                return;
            }

            var self = this;

            this.Loader.show();

            this.$unlock().then(function () {
                self.destroy();
            }, function () {
                self.destroy();
            });
        },

        /**
         * Show info category with general membership information
         */
        $loadSettings: function () {
            var self         = this;
            var lgPrefix     = 'controls.membership.template.settings.';
            var PanelContent = this.getContent();

            PanelContent.set(
                'html',
                Mustache.render(templateSettings, {
                    headerSettings  : QUILocale.get(lg, lgPrefix + 'headerSettings'),
                    labelTitle      : QUILocale.get(lg, lgPrefix + 'labelTitle'),
                    labelDescription: QUILocale.get(lg, lgPrefix + 'labelDescription'),
                    labelContent    : QUILocale.get(lg, lgPrefix + 'labelContent'),
                    headerDuration  : QUILocale.get(lg, lgPrefix + 'headerDuration'),
                    labelDuration   : QUILocale.get(lg, lgPrefix + 'labelDuration'),
                    labelAutoRenew  : QUILocale.get(lg, lgPrefix + 'labelAutoRenew'),
                    autoRenew       : QUILocale.get(lg, lgPrefix + 'autoRenew')
                })
            );

            this.Loader.show();

            var Form = PanelContent.getElement('form');

            QUIFormUtils.setDataToForm(this.$Membership, Form);

            QUI.parse(PanelContent).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Unload the Category and set all settings
         *
         * @param {Boolean} [clear] - Clear the html, default = true
         */
        unloadCategory: function (clear) {
            var i, len, Elm, name, tok, namespace;

            if (typeof clear === 'undefined') {
                clear = true;
            }

            var Body   = this.getBody(),
                Form   = Body.getElement('form'),
                values = {};

            if (!Form) {
                return;
            }

            var attributeInputs = Form.getElements(
                '.quiqqer-memberships-membership-edit-attribute'
            );

            for (i = 0, len = attributeInputs.length; i < len; i++) {
                Elm  = attributeInputs[i];
                name = Elm.name;

                if (name === '') {
                    continue;
                }

                if (Elm.type === 'radio' || Elm.type === 'checkbox') {
                    if (Elm.checked) {
                        values[name] = 1;
                    } else {
                        values[name] = 0;
                    }

                    continue;
                }

                values[name] = Elm.value;
            }

            // set the values to the $Membership object
            for (namespace in values) {
                if (!values.hasOwnProperty(namespace)) {
                    continue;
                }

                this.$Membership[namespace] = values[namespace];
            }

            //if (this.$Control && clear) {
            //    this.$Control.destroy();
            //    this.$Control = null;
            //}
        },

        /**
         * event : click on category button
         *
         * @param {Object} Category - qui/controls/buttons/Button
         */
        $onCategoryActive: function (Category) {
            if (!this.$refreshMode) {
                this.unloadCategory();
            }

            //this.loadCategory(Category);
        },

        /**
         * Send the configuration to the server
         */
        save: function () {
            this.fireEvent('save', [this]);

            this.unloadCategory(false);

            var self   = this;
            var inList = {};

            // filter controls with save method
            var saveable = QUI.Controls.getControlsInElement(this.getBody())
                .filter(function (Control) {
                    if (Control.getId() in inList) {
                        return false;
                    }

                    if (typeof Control.save === 'undefined') {
                        return false;
                    }

                    inList[Control.getId()] = true;
                    return true;
                });

            var promises = saveable.map(function (Control) {
                return Control.save();
            }).filter(function (Promise) {
                return typeof Promise.then == 'function';
            });

            if (!promises.length) {
                promises = [Promise.resolve()];
            }

            this.Loader.show();

            Promise.all(promises).then(function () {
                var Save = self.getButtonBar().getElement('save');

                Save.setAttribute('textimage', 'fa fa-refresh fa-spin');

                Memberships.updateMembership(
                    self.$Membership.id,
                    self.$Membership
                ).then(function (success) {
                    self.Loader.hide();
                    Save.setAttribute('textimage', 'fa fa-save');

                    if (!success) {
                        return;
                    }

                    self.$onCreate();
                });
            });
        },

        /**
         * Check if this membership is currently edited by another user
         */
        $checkLock: function () {
            if (!this.$lockKey || !this.$initialLoad) {
                return;
            }

            var self = this;

            this.Loader.show();

            QUILocker.isLocked(this.$lockKey, 'quiqqer/memberships').then(function (locked) {
                self.Loader.hide();

                if (locked !== false) {
                    self.$isLocked = true;
                    self.$showLockInfo();
                    return;
                }

                self.$isLocked = false;
                self.$lock();
            });
        },

        /**
         * Lock panel for current session user
         *
         * @returns {Promise}
         */
        $lock: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_lock', resolve, {
                    'package'   : 'quiqqer/memberships',
                    membershipId: self.getAttribute('membershipId'),
                    lockKey     : self.$lockKey,
                    onError     : reject
                });
            });
        },

        /**
         * Unlock panel
         *
         * @returns {Promise}
         */
        $unlock: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_memberships_ajax_memberships_unlock', resolve, {
                    'package'   : 'quiqqer/memberships',
                    membershipId: self.getAttribute('membershipId'),
                    lockKey     : self.$lockKey,
                    onError     : reject
                });
            });
        },

        /**
         * Shows lock info
         */
        $showLockInfo: function () {
            var self = this;

            QUIAjax.get('package_quiqqer_memberships_ajax_memberships_canUnlock', function (canUnlock) {
                var lockInfo = '';

                if (canUnlock) {
                    lockInfo = QUILocale.get(lg, 'controls.memberships.membership.lock.unlock.info');
                } else {
                    lockInfo = QUILocale.get(lg, 'controls.memberships.membership.lock.info');
                }

                var LockInfoElm = new Element('div', {
                    'class': 'quiqqer-memberships-membership-lock-info',
                    html   : '<span class="fa fa-lock quiqqer-memberships-membership-lock-info-icon"></span>' +
                    '<h1>' + QUILocale.get(lg, 'controls.memberships.membership.lock.title') + '</h1>' +
                    '<p>' + lockInfo + '</p>' +
                    '<div class="quiqqer-memberships-membership-lock-btn"></div>'
                }).inject(self.$Elm);

                if (canUnlock) {
                    new QUIButton({
                        text     : QUILocale.get(lg, 'controls.memberships.membership.lock.btn.ignore.text'),
                        textimage: 'fa fa-unlock',
                        events   : {
                            onClick: function () {
                                self.Loader.show();

                                self.$lock().then(function (success) {
                                    self.Loader.hide();

                                    if (success) {
                                        self.$isLocked = false;
                                        LockInfoElm.destroy();
                                    }
                                }, function () {
                                    self.destroy();
                                });
                            }
                        }
                    }).inject(
                        LockInfoElm.getElement('.quiqqer-memberships-membership-lock-btn')
                    );
                }

                new QUIButton({
                    text     : QUILocale.get(lg, 'controls.memberships.membership.lock.btn.close.text'),
                    textimage: 'fa fa-close',
                    events   : {
                        onClick: function () {
                            self.destroy();
                        }
                    }
                }).inject(
                    LockInfoElm.getElement('.quiqqer-memberships-membership-lock-btn')
                );
            }, {
                'package': 'quiqqer/memberships'
            });
        }
    });
});
