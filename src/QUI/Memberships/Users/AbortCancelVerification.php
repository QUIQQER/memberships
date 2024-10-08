<?php

namespace QUI\Memberships\Users;

use QUI;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Verification\Verifier;

/**
 * Class CancelVerification
 *
 * Verification process for abortion of MembershipUser cancellation by frontend user
 */
class AbortCancelVerification extends QUI\Verification\AbstractVerification
{
    /**
     * Get the duration of a Verification (minutes)
     *
     * @return int|false - duration in minutes;
     * if this method returns false use the module setting default value
     * @throws \QUI\Exception
     */
    public function getValidDuration(): bool|int
    {
        $MembershipUser = MembershipUsersHandler::getInstance()->getChild($this->getIdentifier());
        $endDate = $MembershipUser->getAttribute('endDate');
        $endDate = strtotime($endDate) / 60; // minutes
        $now = time() / 60; // minutes

        return $endDate - $now;
    }

    /**
     * Execute this method on successful verification
     *
     * @return void
     * @throws \QUI\Exception
     */
    public function onSuccess(): void
    {
        /** @var MembershipUser $MembershipUser */
        $MembershipUser = MembershipUsersHandler::getInstance()->getChild($this->getIdentifier());
        $MembershipUser->setEditUser(QUI::getUsers()->getSystemUser());
        $MembershipUser->confirmAbortCancel();
    }

    /**
     * Execute this method on unsuccessful verification
     *
     * @return void
     */
    public function onError(): void
    {
        // nothing
    }

    /**
     * This message is displayed to the user on successful verification
     *
     * @return string
     * @throws \QUI\Exception
     */
    public function getSuccessMessage(): string
    {
        /** @var MembershipUser $MembershipUser */
        $MembershipUser = MembershipUsersHandler::getInstance()->getChild($this->getIdentifier());
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
     * @param string $reason - The reason for the error (see \QUI\Verification\Verifier::REASON_)
     * @return string
     */
    public function getErrorMessage($reason): string
    {
        switch ($reason) {
            case Verifier::ERROR_REASON_EXPIRED:
                $msg = QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'verification.abortcancel.error.expired'
                );
                break;

            case Verifier::ERROR_REASON_ALREADY_VERIFIED:
                $msg = QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'verification.abortcancel.error.already_verified'
                );
                break;

            default:
                $msg = QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'verification.abortcancel.error.general'
                );
        }

        return $msg;
    }

    /**
     * Automatically redirect the user to this URL on successful verification
     *
     * @return string|false - If this method returns false, no redirection takes place
     */
    public function getOnSuccessRedirectUrl(): bool|string
    {
        return false;
    }

    /**
     * Automatically redirect the user to this URL on unsuccessful verification
     *
     * Hint: This requires that an active Verification with the given identifier exists!
     *
     * @return string|false - If this method returns false, no redirection takes place
     */
    public function getOnErrorRedirectUrl(): bool|string
    {
        return false;
    }
}
