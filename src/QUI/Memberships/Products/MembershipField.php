<?php

/**
 * This file contains QUI\ERP\Tags\Field
 */

namespace QUI\Memberships\Products;

use QUI;
use QUI\ERP\Products;
use QUI\Memberships\Handler as MembershipsHandler;

/**
 * Class MembershipField
 *
 * @author www.pcsg.de (Patrick Müller)
 */
class MembershipField extends Products\Field\Field
{
    /**
     * Field type
     */
    const TYPE = 'memberships.membership';

    /**
     * Fixed field ID for this field
     */
    const FIELD_ID = 102;

    /**
     * @var bool
     */
    public $searchable = true;

    /**
     * Column type for database table (cache column)
     *
     * @var string
     */
    protected $columnType = 'BIGINT';

    /**
     * Cleanup the value, the value is valid now
     *
     * @param mixed $value
     * @return int
     */
    public function cleanup($value)
    {
        return (int)$value;
    }

    /**
     * Check the value
     * is the value valid for the field type?
     *
     * @param mixed $value
     * @throws \QUI\ERP\Products\Field\Exception
     */
    public function validate($value)
    {
        if (empty($value)) {
            return;
        }

        $value = (int)$value;

        try {
            MembershipsHandler::getInstance()->getChild($value);
        } catch (\Exception $Exception) {
            throw new Products\Field\Exception(array(
                'quiqqer/products',
                'exception.field.invalid',
                array(
                    'fieldId'    => $this->getId(),
                    'fieldTitle' => $this->getTitle(),
                    'fieldType'  => $this->getType()
                )
            ));
        }
    }

    /**
     * @return string
     */
    public function getJavaScriptControl()
    {
        return 'package/quiqqer/memberships/bin/controls/products/MembershipField';
    }

    /**
     * Return the view
     *
     * @return \QUI\ERP\Products\Field\View
     */
    public function getFrontendView()
    {
        return new FieldFrontendView($this->getFieldDataForView());
    }

//    /**
//     * Return the field data for a view
//     *
//     * @return array
//     */
//    protected function getFieldDataForView()
//    {
//        $attributes = $this->getAttributes();
//
//        $tags     = $this->getValue();
//        $viewTags = array();
//
//        foreach ($tags as $lang => $langTags) {
//            if (!isset($viewTags[$lang])) {
//                $viewTags[$lang] = array();
//            }
//
//            foreach ($langTags as $tagData) {
//                $viewTags[$lang][] = $tagData['tag'];
//            }
//        }
//
//        $attributes['value'] = $viewTags;
//
//        return $attributes;
//    }
}
