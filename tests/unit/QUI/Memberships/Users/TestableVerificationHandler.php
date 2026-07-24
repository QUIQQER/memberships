<?php

namespace QUI\Memberships\Users;

use QUI\Verification\Entity\AbstractVerification;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationErrorReason;

class TestableVerificationHandler extends AbstractMembershipUserLinkVerificationHandler
{
    public function getValidDuration(AbstractVerification $verification): ?int
    {
        return null;
    }

    public function onSuccess(LinkVerification $verification): void
    {
    }

    public function getSuccessMessage(LinkVerification $verification): string
    {
        return '';
    }

    public function getErrorMessage(
        LinkVerification $verification,
        VerificationErrorReason $reason
    ): string {
        return '';
    }

    public function getMembershipUserPublic(LinkVerification $verification): MembershipUser
    {
        return parent::getMembershipUser($verification);
    }
}
