<?php

namespace QUI\Memberships;

use QUI;
use QUI\Package\Package;
use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Products\MembershipField;
use QUI\ERP\Products\Handler\Fields as ProductFields;
use QUI\ERP\Products\Handler\Categories as ProductCategories;
use QUI\ERP\Products\Handler\Search as ProductSearchHandler;
use QUI\ERP\Products\Product\Product;

/**
 * Class Events
 *
 * Basic events for quiqqer/memberships
 */
class Events
{
    /**
     * quiqqer/quiqqer: onPackageSetup
     *
     * @param Package $Package
     * @return void
     */
    public static function onPackageSetup(Package $Package)
    {
        if ($Package->getName() !== 'quiqqer/memberships') {
            return;
        }

        $packages = Utils::getInstalledMembershipPackages();

        foreach ($packages as $package) {
            switch ($package) {
                case 'quiqqer/products':
                    self::createProductFields();
                    self::createProductCategory();
                    break;

                case 'quiqqer/contracts':
                    // @todo setup routine for quiqqer/contracts
                    break;
            }
        }
    }

    /**
     * quiqqer/products: onQuiqqerProductsProductDelete
     *
     * @param Product $Product
     * @return void
     */
    public static function onQuiqqerProductsProductDelete(Product $Product)
    {
        // check if Product is assigned to a Membership
        $membershipId = $Product->getFieldValue(MembershipsHandler::PRODUCTS_FIELD_MEMBERSHIP);

        if (empty($membershipId)) {
            return;
        }

        // delete Product ID from MembershipUsers
        try {
            $Membership      = MembershipsHandler::getInstance()->getChild($membershipId);
            $MembershipUsers = MembershipUsersHandler::getInstance();

            $membershipUserIds = $Membership->searchUsers(array(
                'productId' => $Product->getId()
            ));

            foreach ($membershipUserIds as $membershipUserId) {
                $MembershipUser = $MembershipUsers->getChild($membershipUserId);
                $MembershipUser->setAttribute('productId', null);
                $MembershipUser->update();
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class . ' :: onQuiqqerProductsProductDelete -> '
                . $Exception->getMessage()
            );
        }
    }

    /**
     * quiqqer/quiqqer: onUserSave
     *
     * @param QUI\Users\User $User
     * @return void
     */
    public static function onUserSave(QUI\Users\User $User)
    {
        $DefaultMembership = MembershipsHandler::getDefaultMembership();

        if ($DefaultMembership === false) {
            return;
        }

        try {
            $DefaultMembership->getMembershipUser($User->getId());
        } catch (\Exception $Exception) {
            if ($Exception->getCode() !== 404) {
                return;
            }

            $DefaultMembership->addUser($User);
        }
    }

    /**
     * quiqqer/products
     *
     * Create a Membership field with a fixed id and a membership flag field that
     * identifies a product as a Membership product
     *
     * @return void
     */
    protected static function createProductFields()
    {
        $L = new QUI\Locale();

        // Membership field
        $translations = array(
            'de' => '',
            'en' => ''
        );

        foreach ($translations as $l => $t) {
            $L->setCurrent($l);
            $translations[$l] = $L->get(
                'quiqqer/memberships',
                'products.field.membership'
            );
        }

        try {
            $NewField = ProductFields::createField(array(
                'id'            => MembershipsHandler::PRODUCTS_FIELD_MEMBERSHIP,
                'type'          => MembershipField::TYPE,
                'titles'        => $translations,
                'workingtitles' => $translations
            ));

            $NewField->setAttribute('search_type', ProductSearchHandler::SEARCHTYPE_TEXT);
            $NewField->save();
        } catch (\QUI\ERP\Products\Field\Exception $Exception) {
            // nothing, field exists
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(self::class . ' :: createProductFields');
            QUI\System\Log::writeException($Exception);
        }

        // membership flag field
        $translations = array(
            'de' => '',
            'en' => ''
        );

        foreach ($translations as $l => $t) {
            $L->setCurrent($l);
            $translations[$l] = $L->get(
                'quiqqer/memberships',
                'products.field.membershipflag'
            );
        }

        try {
            $NewField = ProductFields::createField(array(
                'id'            => MembershipsHandler::PRODUCTS_FIELD_MEMBERSHIPFLAG,
                'type'          => ProductFields::TYPE_BOOL,
                'titles'        => $translations,
                'workingtitles' => $translations
            ));

            $NewField->setAttribute('search_type', ProductSearchHandler::SEARCHTYPE_BOOL);
            $NewField->save();

            // add Flag field to backend search
            $BackendSearch                    = ProductSearchHandler::getBackendSearch();
            $searchFields                     = $BackendSearch->getSearchFields();
            $searchFields[$NewField->getId()] = true;

            $BackendSearch->setSearchFields($searchFields);
        } catch (\QUI\ERP\Products\Field\Exception $Exception) {
            // nothing, field exists
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(self::class . ' :: createProductFields');
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Create a product category for memberships
     *
     * @return void
     */
    protected static function createProductCategory()
    {
        $Category = MembershipsHandler::getProductCategory();

        // do not create a product category if a default category has already been set
        if ($Category !== false) {
            return;
        }

        try {
            $Category = ProductCategories::createCategory();
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(self::class . ' :: createProductCategory()');
            QUI\System\Log::writeException($Exception);

            return;
        }

        $catId  = $Category->getId();
        $titles = array(
            'de' => '',
            'en' => ''
        );

        $descriptions = array(
            'de' => '',
            'en' => ''
        );

        $L = new QUI\Locale();

        foreach ($titles as $l => $t) {
            $L->setCurrent($l);
            $t = $L->get('quiqqer/memberships', 'products.category.title');
            $d = $L->get('quiqqer/memberships', 'products.category.description');

            $titles[$l]                 = $t;
            $titles[$l . '_edit']       = $t;
            $descriptions[$l]           = $d;
            $descriptions[$l . '_edit'] = $d;
        }

        // change title and description
        QUI\Translator::edit(
            'quiqqer/products',
            'products.category.' . $catId . '.title',
            'quiqqer/products',
            array_merge(
                $titles,
                array(
                    'datatype' => 'php,js',
                    'html'     => 0
                )
            )
        );

        QUI\Translator::edit(
            'quiqqer/products',
            'products.category.' . $catId . '.description',
            'quiqqer/products',
            array_merge(
                $descriptions,
                array(
                    'datatype' => 'php,js',
                    'html'     => 0
                )
            )
        );

        // assign Membership Field to category
        $MembershipField = MembershipsHandler::getProductMembershipField();

        if ($MembershipField !== false) {
            $Category->addField($MembershipField);
        }

        $MembershipFlagField = MembershipsHandler::getProductMembershipFlagField();

        if ($MembershipFlagField !== false) {
            $Category->addField($MembershipFlagField);
        }

        $Category->save();

        // set new category as default product category for memberships
        $Conf = QUI::getPackage('quiqqer/memberships')->getConfig();
        $Conf->set('products', 'categoryId', $catId);
        $Conf->save();
    }
}
