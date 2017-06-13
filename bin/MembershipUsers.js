/**
 * MembershipUsers handler
 *
 * @module package/quiqqer/memberships/bin/MembershipUsers
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require package/quiqqer/memberships/bin/classes/MembershipUsers
 */
define('package/quiqqer/memberships/bin/MembershipUsers', [

    'package/quiqqer/memberships/bin/classes/MembershipUsers'

], function (MembershipUsersHandler) {
    "use strict";

    return new MembershipUsersHandler();
});
