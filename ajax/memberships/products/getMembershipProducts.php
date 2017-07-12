<?php

use QUI\Memberships\Handler as MembershipsHandler;
use QUI\ERP\Products\Handler\Fields as ProductFields;

/**
 * Get list of Products that have a specific Membership assigned
 *
 * @param int $membershipId
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_products_getMembershipProducts',
    function ($membershipId) {
        $Memberships = MembershipsHandler::getInstance();
        $Membership  = $Memberships->getChild((int)$membershipId);
        $productData = array();

        /** @var \QUI\ERP\Products\Product\Product $Product */
        foreach ($Membership->getProducts() as $Product) {
            $productData[] = array(
                'id'        => $Product->getId(),
                'title'     => $Product->getTitle(),
                'articleNo' => $Product->getFieldValue(ProductFields::FIELD_PRODUCT_NO)
            );
        }

        return $productData;
    },
    array('membershipId'),
    'Permission::checkAdminUser'
);
