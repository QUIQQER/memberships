<?php

namespace QUI\Memberships\Users;

use QUI;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Verification\Entity\AbstractVerification;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationErrorReason;
use quiqqer\memberships\src\QUI\Memberships\Users\AbstractMembershipUserLinkVerificationHandler;

/**
 * Class CancelVerification
 *
 * Verification process for MembershipUser cancellation by frontend user
 */
class CancelVerification extends AbstractMembershipUserLinkVerificationHandler
{
    /**
     * Get the duration of a Verification (minutes)
     *
     * @param AbstractVerification $verification
     * @return int|null - duration in minutes;
     * if this method returns false use the module setting default value
     * @throws QUI\Exception
     */
    public function getValidDuration(AbstractVerification $verification): ?int
    {
        return (int)MembershipUsersHandler::getSetting('cancelDuration');
    }

    /**
     * Execute this method on successful verification
     *
     * @param LinkVerification $verification
     * @return void
     *
     * @throws QUI\Memberships\Exception
     * @throws QUI\ExceptionStack|QUI\Exception
     */
    public function onSuccess(LinkVerification $verification): void
    {
        $MembershipUser = $this->getMembershipUser($verification);
        $MembershipUser->setEditUser(QUI::getUsers()->getSystemUser());
        $MembershipUser->confirmManualCancel();
    }

    /**
     * This message is displayed to the user on successful verification
     *
     * @param LinkVerification $verification
     * @return string
     */
    public function getSuccessMessage(LinkVerification $verification): string
    {
        return QUI::getLocale()->get(
            'quiqqer/memberships',
            'verification.cancel.success'
        );
    }

    /**
     * This message is displayed to the user on unsuccessful verification
     *
     * @param LinkVerification $verification
     * @param VerificationErrorReason $reason
     * @return string
     */
    public function getErrorMessage(LinkVerification $verification, VerificationErrorReason $reason): string
    {
        return match ($reason) {
            VerificationErrorReason::EXPIRED => QUI::getLocale()->get(
                'quiqqer/memberships',
                'verification.cancel.error.expired'
            ),
            VerificationErrorReason::ALREADY_VERIFIED => QUI::getLocale()->get(
                'quiqqer/memberships',
                'verification.cancel.error.already_cancelled'
            ),
            default => QUI::getLocale()->get(
                'quiqqer/memberships',
                'verification.cancel.error.general'
            ),
        };
    }
}
