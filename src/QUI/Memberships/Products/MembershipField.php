<?php

/**
 * This file contains QUI\ERP\Tags\Field
 */

namespace QUI\Memberships\Products;

use QUI\ERP\Products;
use QUI\Memberships\Handler as MembershipsHandler;

/**
 * Class MembershipField
 *
 * This field represents the assignment of a Membership to a Product
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
     * @var bool
     */
    public bool $searchable = true;

    /**
     * Column type for database table (cache column)
     *
     * @var string
     */
    protected string $columnType = 'BIGINT';

    /**
     * Cleanup the value, the value is valid now
     *
     * @param mixed $value
     * @return int
     */
    public function cleanup($value): mixed
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
    public function validate($value): void
    {
        if (empty($value)) {
            return;
        }

        $value = (int)$value;

        try {
            MembershipsHandler::getInstance()->getChild($value);
        } catch (\Exception $Exception) {
            throw new Products\Field\Exception([
                'quiqqer/products',
                'exception.field.invalid',
                [
                    'fieldId' => $this->getId(),
                    'fieldTitle' => $this->getTitle(),
                    'fieldType' => $this->getType()
                ]
            ]);
        }
    }

    /**
     * @return string
     */
    public function getJavaScriptControl(): string
    {
        return 'package/quiqqer/memberships/bin/controls/MembershipSelect';
    }
}
