<?php

namespace quiqqer\memberships\src\QUI\Memberships\Users;

use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Users\MembershipUser;
use QUI\Verification\AbstractLinkVerificationHandler;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationErrorReason;

abstract class AbstractMembershipUserLinkVerificationHandler extends AbstractLinkVerificationHandler
{
    public function __construct(protected ?MembershipUsersHandler $membershipUsersHandler = null)
    {
        if (is_null($this->membershipUsersHandler)) {
            $this->membershipUsersHandler = MembershipUsersHandler::getInstance();
        }
    }

    /**
     * @param LinkVerification $verification
     * @return MembershipUser
     *
     * @throws \QUI\Exception
     */
    protected function getMembershipUser(LinkVerification $verification): MembershipUser
    {
        /** @var MembershipUser $membershipUser */
        $membershipUser = $this->membershipUsersHandler->getChild(
            $verification->getCustomDataEntry('membershipUserId')
        );

        return $membershipUser;
    }

    /**
     * Execute this method on unsuccessful verification
     *
     * @param LinkVerification $verification
     * @param VerificationErrorReason $reason
     * @return void
     */
    public function onError(LinkVerification $verification, VerificationErrorReason $reason): void
    {
        // nothing
    }

    /**
     * Automatically redirect the user to this URL on successful verification
     *
     * @param LinkVerification $verification
     * @return string|null - If this method returns false, no redirection takes place
     */
    public function getOnSuccessRedirectUrl(LinkVerification $verification): ?string
    {
        return null;
    }

    /**
     * Automatically redirect the user to this URL on unsuccessful verification
     *
     * Hint: This requires that an active Verification with the given identifier exists!
     *
     * @param LinkVerification $verification
     * @param VerificationErrorReason $reason
     * @return string|null - If this method returns false, no redirection takes place
     */
    public function getOnErrorRedirectUrl(LinkVerification $verification, VerificationErrorReason $reason): ?string
    {
        return null;
    }
}