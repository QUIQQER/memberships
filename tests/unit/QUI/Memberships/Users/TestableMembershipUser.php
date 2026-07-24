<?php

namespace QUI\Memberships\Users;

use DateTime;
use QUI\Verification\Entity\LinkVerification;

class TestableMembershipUser extends MembershipUser
{
    public function __construct()
    {
    }

    protected function sendAutoExtendMail(): void
    {
    }

    public function sendAutoExtendMailPublic(): void
    {
        parent::sendAutoExtendMail();
    }

    public function getCancelVerificationPublic(): ?LinkVerification
    {
        return parent::getCancelVerification();
    }

    public function getAbortCancelVerificationPublic(): ?LinkVerification
    {
        return parent::getAbortCancelVerification();
    }

    public function formatDatePublic(DateTime | string | null $date): bool | string
    {
        return parent::formatDate($date);
    }
}
