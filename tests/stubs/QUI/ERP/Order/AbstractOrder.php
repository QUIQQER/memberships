<?php

namespace QUI\ERP\Order;

use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\User;

if (!class_exists(AbstractOrder::class)) {
    abstract class AbstractOrder
    {
        public function getPrefixedId(): string
        {
            return '';
        }

        public function getCustomer(): ?User
        {
            return null;
        }

        public function getArticles(): ArticleList
        {
            return new ArticleList();
        }
    }
}
