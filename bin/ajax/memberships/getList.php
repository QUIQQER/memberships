<?php

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Utils\Security\Orthos;
use QUI\Utils\Grid;

/**
 * Get all QUIQQER memberships
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_getList',
    function ($searchParams) {
        $searchParams = Orthos::clearArray(json_decode($searchParams, true));
        $bundles      = array();
        $Users        = QUI::getUsers();
        $Memberships  = new MembershipsHandler();
        $memberships  = array();

        foreach ($Memberships->search($searchParams) as $membership) {

        }

//        /** @var PackageBundle $Bundle */
//        foreach (MembershipsHandler::search($searchParams) as $Bundle) {
//            $data                 = $Bundle->toArray();
//            $data['packagecount'] = count($data['packages']);
//            $data['createdAt']    = date('Y-m-d H:i:s', $data['createdAt']);
//            $data['editAt']       = date('Y-m-d H:i:s', $data['editAt']);
//            $data['createUser']   = $Users->get($data['createUser'])->getName();
//            $data['editUser']     = $Users->get($data['editUser'])->getName();
//
//            unset($data['packages']);
//
//            $bundles[] = $data;
//        }
//
        $Grid = new Grid($searchParams);

        return $Grid->parseResult(
            $bundles,
            $Memberships->countChildren() // @todo ggf. andere methode
        );
    },
    array('searchParams'),
    'Permission::checkAdminUser'
);