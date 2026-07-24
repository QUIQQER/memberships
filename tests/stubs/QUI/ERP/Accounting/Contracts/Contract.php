<?php

namespace QUI\ERP\Accounting\Contracts;

use DateTime;

if (!class_exists(Contract::class)) {
    class Contract
    {
        public function getCleanId(): int
        {
            return 0;
        }

        public function getTerminationDate(): DateTime | false
        {
            return false;
        }

        public function getCycleEndDate(): DateTime | false
        {
            return false;
        }

        public function getNextCycleEndDate(): DateTime | false
        {
            return false;
        }

        public function getCurrentCancelTerminationDate(): DateTime | false
        {
            return false;
        }
    }
}
