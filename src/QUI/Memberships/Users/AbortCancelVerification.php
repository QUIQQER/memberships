<?php

namespace QUI\Memberships\Users;

use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Verification\Verifier;
use QUI;

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
     */
    public function getValidDuration()
    {
        $MembershipUser = MembershipUsersHandler::getInstance()->getChild($this->getIdentifier());
        $endDate        = $MembershipUser->getAttribute('endDate');
        $endDate        = strtotime($endDate) / 60; // minutes
        $now            = time() / 60; // minutes

        return $endDate - $now;
    }

    /**
     * Execute this method on successful verification
     *
     * @return void
     */
    public function onSuccess()
    {
        /** @var MembershipUser $MembershipUser */
        $MembershipUser = MembershipUsersHandler::getInstance()->getChild($this->getIdentifier());
        $MembershipUser->confirmAbortCancel();
    }

    /**
     * Execute this method on unsuccessful verification
     *
     * @return void
     */
    public function onError()
    {
        // nothing
    }

    /**
     * This message is displayed to the user on successful verification
     *
     * @return string
     */
    public function getSuccessMessage()
    {
        /** @var MembershipUser $MembershipUser */
        $MembershipUser = MembershipUsersHandler::getInstance()->getChild($this->getIdentifier());
        $data           = $MembershipUser->getFrontendViewData();

        if ($MembershipUser->getMembership()->isAutoExtend()) {
            $msg = QUI::getLocale()->get(
                'quiqqer/memberships',
                'verification.abortcancel.success.autoExtend',
                array(
                    'endDate' => $data['endDate']
                )
            );
        } else {
            $msg = QUI::getLocale()->get(
                'quiqqer/memberships',
                'verification.abortcancel.success.noAutoExtend',
                array(
                    'endDate' => $data['endDate']
                )
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
    public function getErrorMessage($reason)
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
    public function getOnSuccessRedirectUrl()
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
    public function getOnErrorRedirectUrl()
    {
        return false;
    }
}
