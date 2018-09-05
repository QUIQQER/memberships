<?php

namespace QUI\Memberships;

use QUI;
use QUI\Package\Package;
use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Products\MembershipField;
use QUI\ERP\Products\Handler\Fields as ProductFields;
use QUI\ERP\Products\Field\Field as ProductField;
use QUI\ERP\Products\Handler\Categories as ProductCategories;
use QUI\ERP\Products\Handler\Search as ProductSearchHandler;
use QUI\ERP\Products\Product\Product;
use QUI\ERP\Order\AbstractOrder;

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

        try {
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
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
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
        $membershipFieldId = Handler::getProductMembershipField()->getId();

        if (!$membershipFieldId) {
            return;
        }

        // check if Product is assigned to a Membership
        $membershipId = $Product->getFieldValue($membershipFieldId);

        if (empty($membershipId)) {
            return;
        }

        // delete Product ID from MembershipUsers
        try {
            $Membership      = MembershipsHandler::getInstance()->getChild($membershipId);
            $MembershipUsers = MembershipUsersHandler::getInstance();

            $membershipUserIds = $Membership->searchUsers([
                'productId' => $Product->getId()
            ]);

            foreach ($membershipUserIds as $membershipUserId) {
                $MembershipUser = $MembershipUsers->getChild($membershipUserId);
                $MembershipUser->setAttribute('productId', null);
                $MembershipUser->update();
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(
                self::class.' :: onQuiqqerProductsProductDelete -> '
                .$Exception->getMessage()
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
     * Create necessary membership product fields and save their IDs to the config
     *
     * @return void
     * @throws \QUI\Exception
     */
    protected static function createProductFields()
    {
        $L    = new QUI\Locale();
        $Conf = QUI::getPackage('quiqqer/memberships')->getConfig();

        // Membership field (create new one is not configured)
        $MembershipField = Handler::getProductMembershipField();

        if ($MembershipField === false) {
            $translations = [
                'de' => '',
                'en' => ''
            ];

            foreach ($translations as $l => $t) {
                $L->setCurrent($l);
                $translations[$l] = $L->get(
                    'quiqqer/memberships',
                    'products.field.membership'
                );
            }

            try {
                $MembershipField = ProductFields::createField([
                    'type'          => MembershipField::TYPE,
                    'titles'        => $translations,
                    'workingtitles' => $translations
                ]);

                $MembershipField->setAttribute('search_type', ProductSearchHandler::SEARCHTYPE_TEXT);
                $MembershipField->save();

                // add field id to config
                $Conf->set('products', 'membershipFieldId', $MembershipField->getId());
                $Conf->save();
            } catch (\Exception $Exception) {
                QUI\System\Log::addError(self::class.' :: createProductFields');
                QUI\System\Log::writeException($Exception);
            }
        }

        // Membership flag field (create new one is not configured)
        $MembershipFlagField = Handler::getProductMembershipFlagField();

        if ($MembershipFlagField === false) {
            $translations = [
                'de' => '',
                'en' => ''
            ];

            foreach ($translations as $l => $t) {
                $L->setCurrent($l);
                $translations[$l] = $L->get(
                    'quiqqer/memberships',
                    'products.field.membershipflag'
                );
            }

            try {
                $MembershipFlagField = ProductFields::createField([
                    'type'          => ProductFields::TYPE_BOOL,
                    'titles'        => $translations,
                    'workingtitles' => $translations
                ]);

                $MembershipFlagField->setAttribute('search_type', ProductSearchHandler::SEARCHTYPE_BOOL);
                $MembershipFlagField->save();

                // add Flag field to backend search
                $BackendSearch                               = ProductSearchHandler::getBackendSearch();
                $searchFields                                = $BackendSearch->getSearchFields();
                $searchFields[$MembershipFlagField->getId()] = true;

                $BackendSearch->setSearchFields($searchFields);

                // add field id to config
                $Conf->set('products', 'membershipFlagFieldId', $MembershipFlagField->getId());
                $Conf->save();
            } catch (\Exception $Exception) {
                QUI\System\Log::addError(self::class.' :: createProductFields');
                QUI\System\Log::writeException($Exception);
            }
        }
    }

    /**
     * quiqqer/order: onOrderSuccess
     *
     * @param AbstractOrder $Order
     * @return void
     */
    public static function onOrderSuccess(AbstractOrder $Order)
    {
        /** @var QUI\ERP\Accounting\Article $Article */
        foreach ($Order->getArticles()->getArticles() as $Article) {
            // @todo Membership Field holen und gucken, ob membershipId vergeben ist
            // Wenn ja, dann Benutzer ($Order->getCustomer()) zu Membership hinzufügen
        }
    }

    /**
     * quiqqer/products: onQuiqqerProductsFieldDeleteBefore
     *
     * @param ProductField $Field
     * @throws Exception
     */
    public static function onQuiqqerProductsFieldDeleteBefore(ProductField $Field)
    {
        $MembershipField = Handler::getProductMembershipField();

        if ($MembershipField !== false && $MembershipField->getId() === $Field->getId()) {
            throw new Exception([
                'quiqqer/memberships',
                'exception.Events.onQuiqqerProductsFieldDelete.cannot_delete_field'
            ]);
        }

        $MembershipFlagField = Handler::getProductMembershipFlagField();

        if ($MembershipFlagField !== false && $MembershipFlagField->getId() === $Field->getId()) {
            throw new Exception([
                'quiqqer/memberships',
                'exception.Events.onQuiqqerProductsFieldDelete.cannot_delete_field'
            ]);
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
            QUI\System\Log::addError(self::class.' :: createProductCategory()');
            QUI\System\Log::writeException($Exception);

            return;
        }

        $catId  = $Category->getId();
        $titles = [
            'de' => '',
            'en' => ''
        ];

        $descriptions = [
            'de' => '',
            'en' => ''
        ];

        $L = new QUI\Locale();

        foreach ($titles as $l => $t) {
            $L->setCurrent($l);
            $t = $L->get('quiqqer/memberships', 'products.category.title');
            $d = $L->get('quiqqer/memberships', 'products.category.description');

            $titles[$l]               = $t;
            $titles[$l.'_edit']       = $t;
            $descriptions[$l]         = $d;
            $descriptions[$l.'_edit'] = $d;
        }

        // change title and description
        QUI\Translator::edit(
            'quiqqer/products',
            'products.category.'.$catId.'.title',
            'quiqqer/products',
            array_merge(
                $titles,
                [
                    'datatype' => 'php,js',
                    'html'     => 0
                ]
            )
        );

        QUI\Translator::edit(
            'quiqqer/products',
            'products.category.'.$catId.'.description',
            'quiqqer/products',
            array_merge(
                $descriptions,
                [
                    'datatype' => 'php,js',
                    'html'     => 0
                ]
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
