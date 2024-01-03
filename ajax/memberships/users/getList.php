<?php

/**
 * Get/search QUIQQER membership users
 *
 * @param int $membershipId - Membership ID
 * @param array $searchParams - Search params
 * @return array
 */

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Users\MembershipUser;
use QUI\Utils\Grid;
use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_users_getList',
    function ($membershipId, $searchParams) {
        $searchParams = Orthos::clearArray(json_decode($searchParams, true));
        $Memberships = MembershipsHandler::getInstance();
        $MembershipUsers = MembershipUsersHandler::getInstance();
        $Membership = $Memberships->getChild((int)$membershipId);
        $membershipUsers = [];

//        $Membership->addUser(QUI::getUserBySession());

        foreach ($Membership->searchUsers($searchParams) as $membershipUserId) {
            /** @var MembershipUser $MembershipUser */
            $MembershipUser = $MembershipUsers->getChild($membershipUserId);
            $membershipUsers[] = $MembershipUser->getBackendViewData();
        }

        /** @var \QUI\Memberships\Users\MembershipUser $TEST */
//        $TEST = $MembershipUsers->getChild(26);
//        $TEST->startManualCancel();

        $Grid = new Grid($searchParams);

        return $Grid->parseResult(
            $membershipUsers,
            $Membership->searchUsers($searchParams, false, true)
        );
    },
    ['membershipId', 'searchParams'],
    'Permission::checkAdminUser'
);
