<?php

namespace QUI\Memberships;

use QUI\CRUD\Factory;
use QUI\Utils\Grid;
use QUI;

class Handler extends Factory
{
    /**
     * @inheritdoc
     * @return string
     */
    public function getDataBaseTableName()
    {
        return 'quiqqer_memberships';
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function getChildClass()
    {
        return Membership::class;
    }

    /**
     * @inheritdoc
     * @return array
     */
    public function getChildAttributes()
    {
        return array(
            'title',
            'description',
            'content',
            'duration',
            'groupIds',
            'autoRenew',
            'editDate',
            'editUser',
            'createDate',
            'createUser',

            // these fields require quiqqer/order
            'paymentInterval',
            'netPrice',
            'netPriceOffer',
//            'grossPrice',
            'paymentMethodIds'

            // @todo additional fields for quiqqer/contracts
        );
    }

    /**
     * Search memberships
     *
     * @param array $searchParams
     * @param bool $countOnly (optional) - get count for search result only [default: false]
     * @return array
     */
    public function search($searchParams, $countOnly = false)
    {
        $memberships = array();
        $Grid        = new Grid($searchParams);
        $gridParams  = $Grid->parseDBParams($searchParams);

        $whereOr = array();

        if (!empty($searchParams['search'])) {
            $search = $searchParams['search'];

            $whereOr['title'] = array(
                'type'  => '%LIKE%',
                'value' => $search
            );

            $whereOr['description'] = array(
                'type'  => '%LIKE%',
                'value' => $search
            );

            $whereOr['content'] = array(
                'type'  => '%LIKE%',
                'value' => $search
            );
        }

        if ($countOnly) {
            $result = QUI::getDataBase()->fetch(array(
                'count'    => 1,
                'from'     => $this->getDataBaseTableName(),
                'where_or' => $whereOr
            ));

            return current(current($result));
        }

        $result = QUI::getDataBase()->fetch(array(
            'select'   => array(
                'id'
            ),
            'from'     => $this->getDataBaseTableName(),
            'where_or' => $whereOr,
            'limit'    => $gridParams['limit']
        ));

        foreach ($result as $row) {
            $memberships[] = $this->getChild($row['id']);
        }

        return $memberships;
    }
}
