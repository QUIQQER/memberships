/**
 * Display and edit data of a single membership
 *
 * @authro www.pcsg.de (Patrick Müller)
 * @module package/quiqqer/memberships/bin/controls/Membership
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 * @require qui/controls/buttons/Button
 * @require qui/utils/Form
 * @require qui/utils/Object
 * @require Ajax
 * @require Locale
 * @require Mustache
 * @require utils/Lock
 * @require package/quiqqer/memberships/bin/Memberships
 * @require text!package/quiqqer/memberships/bin/controls/Membership.Settings.html
 * @require css!package/quiqqer/memberships/bin/controls/Membership.css
 * @require css!controls/desktop/panels/XML.css
 *
 * @event onSave [this] - If the user saves the membership data
 */
define('package/quiqqer/memberships/bin/controls/Membership', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/utils/Form',

    'Ajax',
    'Locale',
    'Mustache',

    'utils/Lock',

    'package/quiqqer/memberships/bin/Memberships',
    'package/quiqqer/memberships/bin/controls/users/MembershipUsers',
    'package/quiqqer/memberships/bin/controls/users/MembershipUsersArchive',

    'text!package/quiqqer/memberships/bin/controls/Membership.Settings.html',
    'css!package/quiqqer/memberships/bin/controls/Membership.css',
    'css!controls/desktop/panels/XML.css'

], function (QUI, QUIPanel, QUIButton, QUIFormUtils, QUIAjax, QUILocale, Mustache,
             QUILocker, Memberships, MembershipUsers, MembershipUsersArchive, templateSettings) {
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
            '$showUserSearch',
            '$hideUserSearch',
            '$startSearch',
            'refresh'
        ],

        options: {
            id  : false,
            icon: 'fa fa-id-card'
        },

        initialize: function (options) {
            this.parent(options);

            this.$Control         = null;
            this.$Membership      = null;
            this.$lockKey         = false;
            this.$initialLoad     = true;
            this.$isLocked        = true;
            this.$refreshMode     = false;
            this.$Search          = null;
            this.$CurrentUserList = null;
            this.$searchUsed      = false;

            this.addEvents({
                onCreate : this.$onCreate,
                onDestroy: this.$onDestroy,
                onSearch : this.$onUserSearch
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
                        self.refresh();
                    }
                }
            });

            // search input
            this.$Search = new Element('div', {
                'class': 'quiqqer-memberships-membership-search quiqqer-memberships-membership-search__hidden',
                html   : '<input type="text"/><span class="fa fa-search"></span>'
            });

            this.$Search.getElement('input').setProperty(
                'placeholder',
                QUILocale.get(lg, 'controls.membership.search.input.placeholder')
            );

            this.$Search.getElement('input').addEvents({
                keyup: function (event) {
                    if (event.code === 13) {
                        var val   = event.target.value.trim();
                        var Input = self.$Search.getElement('input');

                        if (val === '') {
                            Input.value = '';
                            Input.focus();
                            return;
                        }

                        self.$startSearch(val);
                    }
                }
            });

            this.$Search.getElement('span').addEvents({
                click: function (event) {
                    var SearchIcon = event.target;
                    var Input      = self.$Search.getElement('input');

                    if (self.$searchUsed) {
                        SearchIcon.removeClass('fa-times');
                        SearchIcon.addClass('fa-search');
                        Input.value = '';
                        Input.focus();
                        self.$searchUsed = false;
                        self.$startSearch('');
                        return;
                    }

                    if (Input.value.trim() === '') {
                        Input.value = '';
                        Input.focus();
                        return;
                    }

                    self.$searchUsed = true;
                    self.$startSearch(Input.value);
                }
            });

            this.addButton(this.$Search);

            // categories
            this.addCategory(new QUIButton({
                name  : 'settings',
                icon  : 'fa fa-gears',
                text  : QUILocale.get(lg, 'controls.membership.category.settings'),
                events: {
                    onActive: function () {
                        self.unloadCategory();
                        self.$loadSettings();
                    }
                }
            }));

            this.addCategory(new QUIButton({
                name  : 'users',
                icon  : 'fa fa-users',
                text  : QUILocale.get(lg, 'controls.membership.category.users'),
                events: {
                    onActive: function () {
                        self.unloadCategory();
                        self.$loadUsers();
                    }
                }
            }));

            this.addCategory(new QUIButton({
                name  : 'archive',
                icon  : 'fa fa-archive',
                text  : QUILocale.get(lg, 'controls.membership.category.usersArchive'),
                events: {
                    onActive: function () {
                        self.unloadCategory();
                        self.$loadUsersArchive();
                    }
                }
            }));

            this.Loader.show();

            Memberships.getInstalledMembershipPackages().then(function (installedPackages) {
                if (installedPackages.contains('quiqqer/products')) {
                    self.addCategory(new QUIButton({
                        icon  : 'fa fa-money',
                        text  : QUILocale.get(lg, 'controls.membership.category.products'),
                        events: {
                            onActive: self.$loadProducts
                        }
                    }));
                }

                if (installedPackages.contains('quiqqer/contracts')) {
                    self.addCategory(new QUIButton({
                        icon  : 'fa fa-handshake-o',
                        text  : QUILocale.get(lg, 'controls.membership.category.contracts'),
                        events: {
                            onActive: self.$loadContracts
                        }
                    }));
                }

                self.refresh();
            });
        },

        /**
         * Refresh membership data
         */
        refresh: function () {
            var self = this;

            this.Loader.show();

            Memberships.getMembership(this.getAttribute('id')).then(function (Membership) {
                self.$Membership      = Membership;

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

                self.$lockKey = 'membership_' + self.$Membership.id;
                self.$checkLock();

                self.getCategory('settings').setActive();
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
                    labelAutoExtend : QUILocale.get(lg, lgPrefix + 'labelAutoExtend'),
                    autoExtend      : QUILocale.get(lg, lgPrefix + 'autoExtend'),
                    headerGroups    : QUILocale.get(lg, lgPrefix + 'headerGroups'),
                    labelGroups     : QUILocale.get(lg, lgPrefix + 'labelGroups')
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
         * Manage membership users
         */
        $loadUsers: function () {
            var self         = this;
            var PanelContent = this.getContent();

            PanelContent.set('html', '');

            self.$showUserSearch();

            this.$CurrentUserList = new MembershipUsers({
                membershipId: this.$Membership.id
            }).inject(PanelContent);
        },

        /**
         * Show archived membership users
         */
        $loadUsersArchive: function () {
            var self         = this;
            var PanelContent = this.getContent();

            PanelContent.set('html', '');

            self.$showUserSearch();

            this.$CurrentUserList = new MembershipUsersArchive({
                membershipId: this.$Membership.id
            }).inject(PanelContent);
        },

        /**
         * Event: onSearch
         *
         * @param {String} search - search term
         */
        $onUserSearch: function (search) {
            if (!this.$CurrentUserList) {
                return;
            }

            this.$CurrentUserList.setSearchTerm(search);
            this.$CurrentUserList.refresh();
        },

        /**
         * Start search process
         *
         * @param {String} val - Search term
         */
        $startSearch: function (val) {
            if (val !== '') {
                var SearchIcon = this.$Search.getElement('span');

                SearchIcon.removeClass('fa-search');
                SearchIcon.addClass('fa-times');

                this.$searchUsed = true;
            }

            this.fireEvent('search', [val]);
        },

        /**
         * Show user search input in Panel button bar
         */
        $showUserSearch: function () {
            this.$Search.removeClass('quiqqer-memberships-membership-search__hidden');
        },

        /**
         * Hide user search input in Panel button bar
         */
        $hideUserSearch: function () {
            this.$Search.addClass('quiqqer-memberships-membership-search__hidden');
        },

        /**
         * Unload the Category and set all settings
         *
         * @param {Boolean} [clear] - Clear the html, default = true
         */
        unloadCategory: function (clear) {
            var i, len, Elm, name, tok, namespace;

            this.$hideUserSearch();
            this.$CurrentUserList = null;

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
            if (!this.$initialLoad) {
                return;
            }

            var self = this;

            this.Loader.show();

            QUILocker.isLocked('membership_' + this.$Membership.id, 'quiqqer/memberships').then(function (locked) {
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
                    'package': 'quiqqer/memberships',
                    id       : self.$Membership.id,
                    onError  : reject
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
                    'package': 'quiqqer/memberships',
                    id       : self.$Membership.id,
                    onError  : reject
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
                    lockInfo = QUILocale.get(lg, 'controls.membership.lock.unlock.info');
                } else {
                    lockInfo = QUILocale.get(lg, 'controls.membership.lock.info');
                }

                var LockInfoElm = new Element('div', {
                    'class': 'quiqqer-memberships-membership-lock-info',
                    html   : '<span class="fa fa-lock quiqqer-memberships-membership-lock-info-icon"></span>' +
                    '<h1>' + QUILocale.get(lg, 'controls.membership.lock.title') + '</h1>' +
                    '<p>' + lockInfo + '</p>' +
                    '<div class="quiqqer-memberships-membership-lock-btn"></div>'
                }).inject(self.$Elm);

                if (canUnlock) {
                    new QUIButton({
                        text     : QUILocale.get(lg, 'controls.membership.lock.btn.ignore.text'),
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
                    text     : QUILocale.get(lg, 'controls.membership.lock.btn.close.text'),
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
