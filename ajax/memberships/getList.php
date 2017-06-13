<?php

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Utils\Security\Orthos;
use QUI\Utils\Grid;
use QUI\Memberships\Membership;

/**
 * Get/search QUIQQER memberships
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_getList',
    function ($searchParams) {
        $searchParams = Orthos::clearArray(json_decode($searchParams, true));
        $Memberships  = MembershipsHandler::getInstance();
        $memberships  = array();

        foreach ($Memberships->search($searchParams) as $membershipId) {
            /** @var Membership $Membership */
            $Membership    = $Memberships->getChild($membershipId);
            $data          = $Membership->getAttributes();
            $memberships[] = array(
                'id'          => $data['id'],
                'title'       => $Membership->getTitle(),
                'description' => $Membership->getDescription(),
                'duration'    => $data['duration'],
                'userCount'   => 0,
                'autoRenew'   => boolval($data['autoRenew'])
            );
        }

        $Grid = new Grid($searchParams);

        return $Grid->parseResult(
            $memberships,
            $Memberships->countChildren() // @todo ggf. andere methode
        );
    },
    array('searchParams'),
    'Permission::checkAdminUser'
);
