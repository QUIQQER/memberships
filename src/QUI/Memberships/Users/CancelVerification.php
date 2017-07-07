<?php

namespace QUI\Memberships\Users;

use QUI\Verification\VerificationInterface;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Verification\Verifier;
use QUI;

class CancelVerification implements VerificationInterface
{
    /**
     * Verification identifier
     *
     * @var string
     */
    protected $identifier = null;

    /**
     * CancelVerification constructor.
     *
     * @param int $membershipUserId
     */
    public function __construct($membershipUserId)
    {
        $this->identifier = $membershipUserId;
    }

    /**
     * Get a unique identifier that identifies this Verification
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Get the duration of a Verification (minutes)
     *
     * @return int|false - duration in minutes;
     * if this method returns false use the module setting default value
     */
    public static function getValidDuration()
    {
        return (int)MembershipUsersHandler::getSetting('cancelDuration');
    }

    /**
     * Execute this method on successful verification
     *
     * @param string $identifier - Unique Verification identifier
     * @return void
     */
    public static function onSuccess($identifier)
    {
        /** @var MembershipUser $MembershipUser */
        $MembershipUser = MembershipUsersHandler::getInstance()->getChild((int)$identifier);
        $MembershipUser->confirmManualCancel();
    }

    /**
     * Execute this method on unsuccessful verification
     *
     * @param string $identifier - Unique Verification identifier
     * @return void
     */
    public static function onError($identifier)
    {
        // nothing
    }

    /**
     * This message is displayed to the user on successful verification
     *
     * @param string $identifier - Unique Verification identifier
     * @return string
     */
    public static function getSuccessMessage($identifier)
    {
        return QUI::getLocale()->get(
            'quiqqer/memberships',
            'verification.cancel.success'
        );
    }

    /**
     * This message is displayed to the user on unsuccessful verification
     *
     * @param string $identifier - Unique Verification identifier
     * @param string $reason - The reason for the error (see \QUI\Verification\Verifier::REASON_)
     * @return string
     */
    public static function getErrorMessage($identifier, $reason)
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
     * @param string $identifier - Unique Verification identifier
     * @return string|false - If this method returns false, no redirection takes place
     */
    public static function getOnSuccessRedirectUrl($identifier)
    {
        return false;
    }

    /**
     * Automatically redirect the user to this URL on unsuccessful verification
     *
     * Hint: This requires that an active Verification with the given identifier exists!
     *
     * @param string $identifier - Unique Verification identifier
     * @return string|false - If this method returns false, no redirection takes place
     */
    public static function getOnErrorRedirectUrl($identifier)
    {
        return false;
    }
}
