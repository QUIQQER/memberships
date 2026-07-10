<?php

namespace QUI\ERP\Accounting\Contracts;

use DateInterval;
use DateTime;

class Contract
{
    public function getCleanId(): int
    {
    }

    public function getTerminationDate(): DateTime | bool
    {
    }

    public function getExtensionInterval(): DateInterval | bool
    {
    }

    public function getCycleEndDate(): DateTime | bool
    {
    }

    public function getNextCycleEndDate(): DateTime | bool
    {
    }

    public function isInPeriodOfNotice(?DateTime $Date = null): bool
    {
    }

    public function getPeriodOfNoticeInterval(): DateInterval | bool
    {
    }

    public function getCurrentCancelTerminationDate(): DateTime | bool
    {
    }
}
