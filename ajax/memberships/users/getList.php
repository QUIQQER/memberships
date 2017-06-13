<?php

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Utils\Security\Orthos;
use QUI\Utils\Grid;
use QUI\Memberships\Membership;

/**
 * Get/search QUIQQER membership users
 *
 * @param int $membershipId - Membership ID
 * @param array $searchParams - Search params
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_users_getList',
    function ($membershipId, $searchParams) {
        $searchParams    = Orthos::clearArray(json_decode($searchParams, true));
        $Memberships     = MembershipsHandler::getInstance();
        $MembershipUsers = MembershipUsersHandler::getInstance();
        $Users           = QUI::getUsers();
        $Membership      = $Memberships->getChild((int)$membershipId);
        $membershipUsers = array();

        foreach ($Membership->searchUsers($searchParams) as $membershipUserId) {
            /** @var Membership $Membership */
            $MembershipUser = $MembershipUsers->getChild($membershipUserId);
            $data           = $MembershipUser->getAttributes();
            $User           = $Users->get($data['userId']);

            $membershipUsers[] = array(
                'id'            => $data['id'],
                'username'      => $User->getUsername(),
                'userFirstName' => $User->getAttribute('firstname'),
                'userLastName'  => $User->getAttribute('lastname'),
                'addedDate'     => $data['addedDate'],
                'beginDate'     => $data['beginDate'],
                'endDate'       => $data['endDate']
            );
        }

        $Grid = new Grid($searchParams);

        return $Grid->parseResult(
            $membershipUsers,
            $Membership->searchUsers($searchParams, true)
        );
    },
    array('membershipId', 'searchParams'),
    'Permission::checkAdminUser'
);
