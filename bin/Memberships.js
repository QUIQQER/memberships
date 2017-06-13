/**
 * Memberships handler
 *
 * @module package/quiqqer/memberships/bin/Memberships
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require package/quiqqer/memberships/bin/classes/Memberships
 */
define('package/quiqqer/memberships/bin/Memberships', [

    'package/quiqqer/memberships/bin/classes/Memberships'

], function (MembershipsHandler) {
    "use strict";

    return new MembershipsHandler();
});
