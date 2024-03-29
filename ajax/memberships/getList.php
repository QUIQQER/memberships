<?php

/**
 * Get/search QUIQQER memberships
 *
 * @return array
 */

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Membership;
use QUI\Utils\Grid;
use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_getList',
    function ($searchParams) {
        $searchParams = Orthos::clearArray(json_decode($searchParams, true));
        $Memberships = MembershipsHandler::getInstance();
        $memberships = [];

        foreach ($Memberships->search($searchParams) as $membershipId) {
            /** @var Membership $Membership */
            $Membership = $Memberships->getChild($membershipId);
            $data = $Membership->getAttributes();
            $memberships[] = [
                'id' => $data['id'],
                'title' => $Membership->getTitle(),
                'description' => $Membership->getDescription(),
                'duration' => $data['duration'],
                'userCount' => count($Membership->getMembershipUserIds()),
                'autoExtend' => boolval($data['autoExtend'])
            ];
        }

        $Grid = new Grid($searchParams);

        return $Grid->parseResult(
            $memberships,
            $Memberships->countChildren() // @todo ggf. andere methode
        );
    },
    ['searchParams'],
    'Permission::checkAdminUser'
);
