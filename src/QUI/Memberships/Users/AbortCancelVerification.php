<?php

namespace QUI\Memberships\Users;

use QUI\Verification\VerificationInterface;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Verification\Verifier;
use QUI;

/**
 * Class CancelVerification
 *
 * Verification process for abortion of MembershipUser cancellation by frontend user
 */
class AbortCancelVerification implements VerificationInterface
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
     * @param string $identifier - Unique Verification identifier
     * @return int|false - duration in minutes;
     * if this method returns false use the module setting default value
     */
    public static function getValidDuration($identifier)
    {
        $MembershipUser = MembershipUsersHandler::getInstance()->getChild((int)$identifier);
        $endDate        = $MembershipUser->getAttribute('endDate');
        $endDate        = strtotime($endDate) / 60; // minutes
        $now            = time() / 60; // minutes

        return $endDate - $now;
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
        $MembershipUser->confirmAbortCancel();
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
        /** @var MembershipUser $MembershipUser */
        $MembershipUser = MembershipUsersHandler::getInstance()->getChild((int)$identifier);
        $data           = $MembershipUser->getFrontendViewData();

        if ($MembershipUser->getMembership()->isAutoExtend()) {
            $msg = QUI::getLocale()->get(
                'quiqqer/memberships',
                'verification.abortcancel.success.autoExtend', array(
                    'endDate' => $data['endDate']
                )
            );
        } else {
            $msg = QUI::getLocale()->get(
                'quiqqer/memberships',
                'verification.abortcancel.success.noAutoExtend', array(
                    'endDate' => $data['endDate']
                )
            );
        }

        return $msg;
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
