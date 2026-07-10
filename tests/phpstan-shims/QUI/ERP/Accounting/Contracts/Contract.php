<?php

namespace QUI\ERP\Accounting\Contracts;

use DateInterval;
use DateTime;

class Contract
{
    public function getCleanId(): int
    {
    }

    public function getTerminationDate(): DateTime | false
    {
    }

    public function getExtensionInterval(): DateInterval | false
    {
    }

    public function getCycleEndDate(): DateTime | false
    {
    }

    public function getNextCycleEndDate(): DateTime | false
    {
    }

    public function isInPeriodOfNotice(?DateTime $Date = null): bool
    {
    }

    public function getPeriodOfNoticeInterval(): DateInterval | false
    {
    }

    public function getCurrentCancelTerminationDate(): DateTime | false
    {
    }
}
