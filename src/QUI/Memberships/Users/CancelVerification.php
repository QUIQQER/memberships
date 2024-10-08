<?php

namespace QUI\Memberships\Users;

use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Verification\Verifier;
use QUI;

/**
 * Class CancelVerification
 *
 * Verification process for MembershipUser cancellation by frontend user
 */
class CancelVerification extends QUI\Verification\AbstractVerification
{
    /**
     * Get the duration of a Verification (minutes)
     *
     * @return int|false - duration in minutes;
     * if this method returns false use the module setting default value
     */
    public function getValidDuration(): bool|int
    {
        return (int)MembershipUsersHandler::getSetting('cancelDuration');
    }

    /**
     * Execute this method on successful verification
     *
     * @return void
     */
    public function onSuccess(): void
    {
        /** @var MembershipUser $MembershipUser */
        $MembershipUser = MembershipUsersHandler::getInstance()->getChild($this->getIdentifier());
        $MembershipUser->setEditUser(QUI::getUsers()->getSystemUser());
        $MembershipUser->confirmManualCancel();
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
     */
    public function getSuccessMessage(): string
    {
        return QUI::getLocale()->get(
            'quiqqer/memberships',
            'verification.cancel.success'
        );
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
                    'verification.cancel.error.expired'
                );
                break;

            case Verifier::ERROR_REASON_ALREADY_VERIFIED:
                $msg = QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'verification.cancel.error.already_cancelled'
                );
                break;

            default:
                $msg = QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'verification.cancel.error.general'
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
