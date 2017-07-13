/**
 * MembershipUserHistory JavaScript Control
 *
 * View the history log of a specific MembershipUser
 *
 * @module package/quiqqer/memberships/bin/controls/users/MembershipUserHistory
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require qui/controls/loader/Loader
 * @require package/quiqqer/memberships/bin/MembershipUsers
 * @require Locale
 * @require Ajax
 * @require Mustache
 * @require text!package/quiqqer/memberships/bin/controls/users/MembershipUserHistory.html
 * @require css!package/quiqqer/memberships/bin/controls/users/MembershipUserHistory.css
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

    var lg = 'quiqqer/memberships';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/memberships/bin/controls/users/MembershipUserHistory',

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

            this.Loader          = new QUILoader();
            this.$MembershipUser = null;
            this.$history        = [];

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
            var self = this;

            this.Loader.inject(this.$Elm);
            this.Loader.show();

            var mUid = this.getAttribute('membershipUserId');

            Promise.all([
                MembershipUsersHandler.get(mUid),
                MembershipUsersHandler.getHistory(mUid)
            ]).then(function (result) {
                self.Loader.hide();
                self.$MembershipUser = result[0];
                self.$history        = result[1];
                self.$load();
            });
        },

        /**
         * Create elements
         */
        $load: function () {
            var lgPrefix = 'controls.users.membershipuserhistory.template.';
            var username = this.$MembershipUser.fullName;

            if (this.$MembershipUser.username !== this.$MembershipUser.fullName) {
                username += ' (' + this.$MembershipUser.username + ')';
            }

            username += ' [' + this.$MembershipUser.userId + ']';

            this.$Elm.set('html', Mustache.render(template, {
                userLabel      : QUILocale.get(lg, lgPrefix + 'userLabel'),
                membershipLabel: QUILocale.get(lg, lgPrefix + 'membershipLabel'),
                user           : username,
                membership     : this.$MembershipUser.membershipTitle
                + ' [' + this.$MembershipUser.membershipId + ']'
            }));

            var HistoryElm = this.$Elm.getElement(
                '.quiqqer-memberships-membershipuserhistory-history'
            );

            var i = this.$history.length;

            this.$history.forEach(function (Entry) {
                var EntryElm = new Element('div', {
                    'class': 'quiqqer-memberships-membershipuserhistory-history-entry'
                }).inject(HistoryElm);

                // header
                new Element('div', {
                    'class': 'quiqqer-memberships-membershipuserhistory-history-entry-header',
                    html   : '<span class="quiqqer-memberships-membershipuserhistory-history-entry-header-action">' +
                    ' #' + i-- + ' ' +
                    QUILocale.get(lg, 'controls.users.membershipuserhistory.entry.type.' + Entry.type) +
                    '</span>' +
                    '<span class="quiqqer-memberships-membershipuserhistory-history-entry-header-date">' +
                    Entry.time + '<br>' + Entry.user +
                    '</span>'
                }).inject(EntryElm);

                // body
                if (Entry.msg !== '') {
                    var msg = Entry.msg;

                    try {
                        var Message = JSON.decode(Entry.msg);
                        msg         = JSON.stringify(Message, null, 2);
                    } catch (e) {
                        // nothing, msg is not JSON formatted
                    }

                    new Element('div', {
                        'class': 'quiqqer-memberships-membershipuserhistory-history-entry-body',
                        html   : '<pre>' + msg + '</pre>'
                    }).inject(EntryElm);
                }
            });

            if (!Object.getLength(this.$MembershipUser.extraData)) {
                return;
            }

            // extra btn
            new QUIButton({
                text     : QUILocale.get(lg, 'controls.users.membershipuserhistory.btn.extraData'),
                textimage: 'fa fa-file',
                events   : {
                    onClick: this.$showExtraData
                }
            }).inject(
                this.$Elm.getElement(
                    '.quiqqer-memberships-membershipuserhistory-extrabtn'
                )
            );
        },

        $showExtraData: function () {
            var extraData = JSON.stringify(this.$MembershipUser.extraData, null, 2);

            new QUIConfirm({
                maxHeight  : 600,
                maxWidth   : 600,
                'autoclose': true,

                'information': '<pre>' + extraData + '</pre>',
                'title'      : QUILocale.get(lg,
                    'controls.membershipuserhistory.extraData.popup.title'
                ),
                'texticon'   : 'fa fa-file',
                'icon'       : 'fa fa-file',

                cancel_button: false,
                ok_button    : {
                    text     : 'OK',
                    textimage: 'icon-ok fa fa-check'
                }
            }).open();
        }
    });
});
