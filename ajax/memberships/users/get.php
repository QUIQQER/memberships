<?php

use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Users\MembershipUser;

/**
 * Get general data of a MembershipUser
 *
 * @param int $membershipUserId
 * @return array|false - history data or false on error
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_memberships_ajax_memberships_users_get',
    function ($membershipUserId) {
        try {
            $MembershipUsers = MembershipUsersHandler::getInstance();
            /** @var MembershipUser $MembershipUser */
            $MembershipUser = $MembershipUsers->getChild((int)$membershipUserId);
            $QuiqqerUser    = $MembershipUser->getUser();
            $Membership     = $MembershipUser->getMembership();

            return array(
                'id'              => $MembershipUser->getId(),
                'userId'          => $QuiqqerUser->getId(),
                'membershipId'    => $Membership->getId(),
                'membershipTitle' => $Membership->getTitle(),
                'username'        => $QuiqqerUser->getUsername(),
                'fullName'        => $QuiqqerUser->getName(),
                'addedDate'       => $MembershipUser->getAttribute('addedDate'),
                'beginDate'       => $MembershipUser->getAttribute('beginDate'),
                'endDate'         => $MembershipUser->getAttribute('endDate'),
                'archived'        => $MembershipUser->isArchived(),
                'archiveReason'   => $MembershipUser->getAttribute('archiveReason')
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::addError('AJAX :: package_quiqqer_memberships_ajax_memberships_users_getHistory');
            QUI\System\Log::writeException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'message.ajax.general.error',
                    array(
                        'error' => $Exception->getMessage()
                    )
                )
            );

            return false;
        }
    },
    array('membershipUserId'),
    'Permission::checkAdminUser'
);
