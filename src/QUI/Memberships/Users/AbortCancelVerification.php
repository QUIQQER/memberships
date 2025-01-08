<?php

namespace QUI\Memberships\Users;

use QUI;
use QUI\Verification\Entity\AbstractVerification;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationErrorReason;
use quiqqer\memberships\src\QUI\Memberships\Users\AbstractMembershipUserLinkVerificationHandler;

/**
 * Class CancelVerification
 *
 * Verification process for abortion of MembershipUser cancellation by frontend user
 */
class AbortCancelVerification extends AbstractMembershipUserLinkVerificationHandler
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
        /** @var LinkVerification $verification */
        $MembershipUser = $this->getMembershipUser($verification);
        $endDate = $MembershipUser->getAttribute('endDate');
        $endDate = strtotime($endDate) / 60; // minutes
        $now = time() / 60; // minutes

        return $endDate - $now;
    }

    /**
     * Execute this method on successful verification
     *
     * @param LinkVerification $verification
     * @return void
     * @throws QUI\Exception
     */
    public function onSuccess(LinkVerification $verification): void
    {
        $MembershipUser = $this->getMembershipUser($verification);
        $MembershipUser->setEditUser(QUI::getUsers()->getSystemUser());
        $MembershipUser->confirmAbortCancel();
    }

    /**
     * This message is displayed to the user on successful verification
     *
     * @param LinkVerification $verification
     * @return string
     * @throws QUI\Exception
     */
    public function getSuccessMessage(LinkVerification $verification): string
    {
        $MembershipUser = $this->getMembershipUser($verification);
        $Membership = $MembershipUser->getMembership();
        $data = $MembershipUser->getFrontendViewData();

        if ($Membership->isAutoExtend()) {
            $msg = QUI::getLocale()->get(
                'quiqqer/memberships',
                'verification.abortcancel.success.autoExtend',
                [
                    'endDate' => $data['endDate'],
                    'membershipTitle' => $Membership->getTitle()
                ]
            );
        } else {
            $msg = QUI::getLocale()->get(
                'quiqqer/memberships',
                'verification.abortcancel.success.noAutoExtend',
                [
                    'endDate' => $data['endDate'],
                    'membershipTitle' => $Membership->getTitle()
                ]
            );
        }

        return $msg;
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
                'verification.abortcancel.error.expired'
            ),
            VerificationErrorReason::ALREADY_VERIFIED => QUI::getLocale()->get(
                'quiqqer/memberships',
                'verification.abortcancel.error.already_verified'
            ),
            default => QUI::getLocale()->get(
                'quiqqer/memberships',
                'verification.abortcancel.error.general'
            ),
        };
    }
}
