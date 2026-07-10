<?php

namespace QUI\ERP\Order;

use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\User;

abstract class AbstractOrder
{
    public function getPrefixedId(): string
    {
    }

    public function getCustomer(): ?User
    {
    }

    public function getArticles(): ArticleList
    {
    }
}
