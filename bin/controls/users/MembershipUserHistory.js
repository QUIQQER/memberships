/**
 * MembershipUserHistory JavaScript Control
 *
 * View the history log of a specific MembershipUser
 */
define('package/quiqqer/memberships/bin/controls/users/MembershipUserHistory', [

    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',
    'qui/controls/loader/Loader',

    'package/quiqqer/memberships/bin/MembershipUsers',

    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/memberships/bin/controls/users/MembershipUserHistory.html',
    'css!package/quiqqer/memberships/bin/controls/users/MembershipUserHistory.css'

], function (QUIControl, QUIButton, QUIConfirm, QUILoader, MembershipUsersHandler,
             QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    const lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/memberships/bin/controls/users/MembershipUserHistory',

        Binds: [
            '$onInject',
            '$onCreate',
            '$load',
            '$showExtraData'
        ],

        options: {
            membershipUserId: false // ID of MembershipUser (this is NOT the QUIQQER User ID!)
        },

        initialize: function (options) {
            this.parent(options);

            this.Loader = new QUILoader();
            this.$MembershipUser = null;
            this.$history = [];

            this.addEvents({
                onCreate: this.$onCreate,
                onInject: this.$onInject
            });
        },

        /**
         * Event: onCreate
         */
        $onCreate: function () {
            this.$Elm.addClass('quiqqer-memberships-membershipuserhistory');
        },

        /**
         * Event: onImport
         */
        $onInject: function () {
            this.Loader.inject(this.$Elm);
            this.Loader.show();

            const mUid = this.getAttribute('membershipUserId');

            Promise.all([
                MembershipUsersHandler.get(mUid),
                MembershipUsersHandler.getHistory(mUid)
            ]).then((result) => {
                this.Loader.hide();
                this.$MembershipUser = result[0];
                this.$history = result[1];
                this.$load();
            });
        },

        /**
         * Create elements
         */
        $load: function () {
            const lgPrefix = 'controls.users.membershipuserhistory.template.';
            let username = this.$MembershipUser.fullName;
            const getEntryTone = (type) => {
                switch (type) {
                    case 'created':
                    case 'extended':
                    case 'uncancel_by_edit':
                    case 'cancel_abort_confirm':
                        return 'success';

                    case 'cancel_start':
                    case 'cancel_system':
                    case 'cancel_confirm':
                    case 'cancelled':
                    case 'cancel_by_edit':
                    case 'cancel_abort_start':
                        return 'warning';

                    case 'deleted':
                    case 'archived':
                    case 'expired':
                        return 'danger';

                    default:
                        return 'info';
                }
            };

            if (this.$MembershipUser.username !== this.$MembershipUser.fullName) {
                username += ' (' + this.$MembershipUser.username + ')';
            }

            this.$Elm.innerHTML = Mustache.render(template, {
                userLabel: QUILocale.get(lg, lgPrefix + 'userLabel'),
                membershipLabel: QUILocale.get(lg, lgPrefix + 'membershipLabel'),
                user: username,
                membership: this.$MembershipUser.membershipTitle
                    + ' [' + this.$MembershipUser.membershipId + ']'
            });

            const HistoryElm = this.$Elm.querySelector(
                '.quiqqer-memberships-membershipuserhistory-history'
            );

            let i = this.$history.length;

            this.$history.forEach((Entry) => {
                const EntryElm = document.createElement('div');
                EntryElm.className = 'quiqqer-memberships-membershipuserhistory-history-entry ' +
                    'quiqqer-memberships-membershipuserhistory-history-entry--' + getEntryTone(Entry.type);
                HistoryElm.appendChild(EntryElm);

                const HeaderElm = document.createElement('div');
                HeaderElm.className = 'quiqqer-memberships-membershipuserhistory-history-entry-header';
                EntryElm.appendChild(HeaderElm);

                const HeaderMain = document.createElement('div');
                HeaderMain.className = 'quiqqer-memberships-membershipuserhistory-history-entry-header-main';
                HeaderElm.appendChild(HeaderMain);

                const IndexElm = document.createElement('span');
                IndexElm.className = 'quiqqer-memberships-membershipuserhistory-history-entry-number';
                IndexElm.textContent = 'Eintrag #' + i--;
                HeaderMain.appendChild(IndexElm);

                const TypeElm = document.createElement('span');
                TypeElm.className = 'quiqqer-memberships-membershipuserhistory-history-entry-type';
                TypeElm.textContent = QUILocale.get(
                    lg,
                    'controls.users.membershipuserhistory.entry.type.' + Entry.type
                );
                HeaderMain.appendChild(TypeElm);

                const MetaElm = document.createElement('div');
                MetaElm.className = 'quiqqer-memberships-membershipuserhistory-history-entry-meta';
                MetaElm.textContent = Entry.time + ' | ' + Entry.user.replace(/\s*\(\d+\)\s*$/, '');
                HeaderElm.appendChild(MetaElm);

                if (Entry.msg !== '') {
                    let msg = Entry.msg;

                    try {
                        const Message = JSON.parse(Entry.msg);
                        msg = JSON.stringify(Message, null, 2);
                    } catch (e) {
                        // nothing, msg is not JSON formatted
                    }

                    const BodyElm = document.createElement('div');
                    BodyElm.className = 'quiqqer-memberships-membershipuserhistory-history-entry-body';
                    const PreElm = document.createElement('pre');
                    PreElm.textContent = msg;
                    BodyElm.appendChild(PreElm);
                    EntryElm.appendChild(BodyElm);
                }
            });

            if (!Object.getLength(this.$MembershipUser.extraData)) {
                return;
            }

            // extra btn
            new QUIButton({
                text: QUILocale.get(lg, 'controls.users.membershipuserhistory.btn.extraData'),
                textimage: 'fa fa-file',
                events: {
                    onClick: this.$showExtraData
                }
            }).inject(
                this.$Elm.querySelector(
                    '.quiqqer-memberships-membershipuserhistory-extrabtn'
                )
            );
        },

        $showExtraData: function () {
            const extraData = JSON.stringify(this.$MembershipUser.extraData, null, 2);

            new QUIConfirm({
                maxHeight: 600,
                maxWidth: 600,
                'autoclose': true,

                'information': '<pre>' + extraData + '</pre>',
                'title': QUILocale.get(lg,
                    'controls.membershipuserhistory.extraData.popup.title'
                ),
                'texticon': 'fa fa-file',
                'icon': 'fa fa-file',

                cancel_button: false,
                ok_button: {
                    text: 'OK',
                    textimage: 'icon-ok fa fa-check'
                }
            }).open();
        }
    });
});
