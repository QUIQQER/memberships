<?php

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Utils\Security\Orthos;
use QUI\Utils\Grid;
use QUI\Memberships\Users\MembershipUser;

/**
 * Get/search QUIQQER membership users (archived)
 *
 * @param int $membershipId - Membership ID
 * @param array $searchParams - Search params
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_users_getArchiveList',
    function ($membershipId, $searchParams) {
        $searchParams    = Orthos::clearArray(json_decode($searchParams, true));
        $Memberships     = MembershipsHandler::getInstance();
        $MembershipUsers = MembershipUsersHandler::getInstance();
        $Membership      = $Memberships->getChild((int)$membershipId);
        $membershipUsers = array();

        foreach ($Membership->searchUsers($searchParams, true) as $membershipUserId) {
            /** @var MembershipUser $MembershipUser */
            $MembershipUser    = $MembershipUsers->getChild($membershipUserId);
            $membershipUsers[] = $MembershipUser->getBackendViewData();
        }

        $Grid = new Grid($searchParams);

        return $Grid->parseResult(
            $membershipUsers,
            $Membership->searchUsers($searchParams, true, true)
        );
    },
    array('membershipId', 'searchParams'),
    'Permission::checkAdminUser'
);
