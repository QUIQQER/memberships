<?php

namespace QUI\Memberships;

use QUI;
use QUI\Package\Package;
use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Products\MembershipField;
use QUI\ERP\Products\Handler\Fields as ProductFields;
use QUI\ERP\Products\Handler\Categories as ProductCategories;

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

        \QUI\System\Log::writeRecursive("ONPACKAGESETUP MEMBERSHIPS");
        return;

        $packages = MembershipsHandler::getInstance()->getInstalledMembershipPackages();

        foreach ($packages as $package) {
            switch ($package) {
                case 'quiqqer/products':
//                    self::createProductField();
                    self::createProductCategory();
                    break;

                case 'quiqqer/contracts':
                    // @todo setup routine for quiqqer/contracts
                    break;
            }
        }
    }

    /**
     * quiqqer/products
     *
     * Create a Membership field with a fixed id
     *
     * @return void
     */
    protected static function createProductField()
    {
        $L            = new QUI\Locale();
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
            ProductFields::createField(array(
                'id'            => MembershipField::FIELD_ID,
                'type'          => MembershipField::TYPE,
                'systemField'   => 1,
                'titles'        => $translations,
                'workingtitles' => $translations
            ));
        } catch (\QUI\ERP\Products\Field\Exception $Exception) {
            // nothing, field exists
        } catch (\Exception $Exception) {
            QUI\System\Log::addError(self::class . ' :: createProductField');
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
    }

    /**
     * Create products for every membership
     *
     * @return void
     */
    protected static function createProducts()
    {
    }
}
