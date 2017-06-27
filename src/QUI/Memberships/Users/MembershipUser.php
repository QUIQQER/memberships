<?php

namespace QUI\Memberships\Users;

use QUI;
use QUI\CRUD\Child;
use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Utils;
use QUI\Mail\Mailer;
use QUI\Permissions\Permission;

/**
 * Class MembershipUser
 *
 * Represents a user that is assigned to a specific membership
 *
 * @package QUI\Memberships\Users
 */
class MembershipUser extends Child
{
    /**
     * @inheritdoc
     */
    public function update()
    {
        Permission::checkPermission(MembershipUsersHandler::PERMISSION_EDIT_USERS);

        // check certain attributes
        $beginDate = strtotime($this->getAttribute('beginDate'));
        $endDate   = strtotime($this->getAttribute('endDate'));

        if ($beginDate === false
            || $endDate === false
        ) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.membershipuser.wrong.dates'
            ));
        }

        if ($beginDate >= $endDate) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.membershipuser.begin.after.end'
            ));
        }

        parent::update();
    }

    /**
     * Extend the current membership cycle of this membership user
     *
     * @param bool $auto (optional) - Used if the membership is automatically extended.
     * If set to false, the setting membershipusers.extendMode is used [default: true]
     * @return void
     */
    public function extend($auto = true)
    {
        $Membership = $this->getMembership();
        $extendMode = MembershipUsersHandler::getSetting('extendMode');

        if ($auto || $extendMode === 'reset') {
            $start         = time();
            $extendCounter = $this->getAttribute('extendCounter');

            $this->setAttributes(array(
                'beginDate'     => Utils::getFormattedTimestamp($start),
                'endDate'       => $Membership->calcEndDate($start),
                'extendCounter' => $extendCounter + 1
            ));
        } else {
            $endDate = $this->getAttribute('endDate');

            $this->setAttributes(array(
                'endDate' => $Membership->calcEndDate(strtotime($endDate))
            ));
        }

        $historyData = array(
            'start' => $this->getAttribute('beginDate'),
            'end'   => $this->getAttribute('endDate'),
            'auto'  => $auto ? '1' : '0'
        );

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_EXTENDED, json_encode($historyData));
        $this->update();

        // send autoextend mail
        $subject = $this->getUser()->getLocale()->get('quiqqer/memberships', 'templates.mail.autoextend.subject');
        $this->sendMail($subject, dirname(__FILE__, 5) . '/templates/mail_autoextend.html');
    }

    /**
     * Expires this memberships user
     *
     * @return void
     */
    public function expire()
    {
        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_EXPIRED);
        $this->archive(MembershipUsersHandler::ARCHIVE_REASON_EXPIRED);

        // send expire mail
        $subject = $this->getUser()->getLocale()->get('quiqqer/memberships', 'templates.mail.expired.subject');
        $this->sendMail($subject, dirname(__FILE__, 5) . '/templates/mail_expired.html');
    }

    /**
     * Start the manual membership cancellation process
     *
     * Generates a random hash and sends an email to the user
     *
     * @return void
     */
    public function startManualCancel()
    {
        if ($this->isCancelled()) {
            // @todo throw Exception?
            return;
        }

        $cancelDate = Utils::getFormattedTimestamp();
        $cancelHash = md5(openssl_random_pseudo_bytes(256));
        $cancelUrl  = QUI::getRewrite()->getProject()->getVHost(true);
        $cancelUrl  .= URL_OPT_DIR . 'quiqqer/memberships/bin/membership.php';

        $params = array(
            'mid'    => $this->id,
            'hash'   => $cancelHash,
            'action' => 'confirmManualCancel'
        );

        $cancelUrl .= '?' . http_build_query($params);

        // generate random hash
        $this->setAttributes(array(
            'cancelHash' => $cancelHash,
            'cancelDate' => $cancelDate
        ));

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_CANCEL_START);

        // save cancel hash and date to database
        $this->update();

        // send cancellation mail
        $this->sendMail(
            QUI::getLocale()->get('quiqqer/memberships', 'templates.mail.startcancel.subject'),
            dirname(__FILE__, 5) . '/templates/mail_startcancel.html',
            array(
                'cancelDate' => $cancelDate,
                'cancelUrl'  => $cancelUrl
            )
        );
    }

    /**
     * Confirm membership cancellation
     *
     * @param string $confirmHash - cancel hash
     * @return void
     *
     * @throws QUI\Memberships\Exception
     */
    public function confirmManualCancel($confirmHash)
    {
        if ($this->isCancelled()) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.membershipuser.confirmManualCancel.already.cancelled'
            ));
        }

        $cancelHash = $this->getAttribute('cancelHash');

//        if (empty($cancelHash)) {
//            throw new QUI\Memberships\Exception(array(
//                'quiqqer/memberships',
//                'exception.users.membershipuser.confirmManualCancel.no.hash'
//            ));
//        }

        if ($confirmHash !== $cancelHash) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.membershipuser.confirmManualCancel.hash.mismatch'
            ));
        }

        $this->setAttributes(array(
            'cancelled' => true
        ));

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_CANCEL_CONFIRM);
        $this->update();

        // send confirm cancel mail
        $this->sendConfirmCancelMail();
    }

    /**
     * Send mail to user to confirm cancellation
     *
     * @return void
     */
    public function sendConfirmCancelMail()
    {
        $subject = $this->getUser()->getLocale()->get('quiqqer/memberships', 'templates.mail.confirmcancel.subject');
        $this->sendMail($subject, dirname(__FILE__, 5) . '/templates/mail_confirmcancel.html');
    }

    /**
     * Cancel membership
     *
     * @return void
     */
    public function cancel()
    {
        $this->archive(MembershipUsersHandler::ARCHIVE_REASON_CANCELLED);

        // send expired mail
        $subject = $this->getUser()->getLocale()->get('quiqqer/memberships', 'templates.mail.expired.subject');
        $this->sendMail($subject, dirname(__FILE__, 5) . '/templates/mail_expired.html');
    }

    /**
     * Check if this user has cancelled his membership
     *
     * @return mixed
     */
    public function isCancelled()
    {
        return boolval($this->getAttribute('cancelled'));
    }

    /**
     * Delete membership user and remove QUIQQER user from all unique groups
     *
     * A deleted membership user is not removed from the database but set to "archived".
     *
     * @return void
     */
    public function delete()
    {
        Permission::checkPermission(MembershipUsersHandler::PERMISSION_EDIT_USERS);

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_DELETED);

        // do not delete, just set to archived
        $this->archive(MembershipUsersHandler::ARCHIVE_REASON_DELETED);
    }

    /**
     * Set User to all membership QUIQQER groups
     *
     * @return void
     */
    public function addToGroups()
    {
        $groupIds = $this->getMembership()->getGroupIds();
        $User     = $this->getUser();

        foreach ($groupIds as $groupId) {
            $User->addToGroup($groupId);
        }

        $User->save(QUI::getUsers()->getSystemUser());
    }

    /**
     * Removes the membership user from all quiqqer groups (that he is part of because of
     * his membership)
     *
     * @return void
     */
    protected function removeFromGroups()
    {
        $Groups             = QUI::getGroups();
        $User               = QUI::getUsers()->get($this->getUserId());
        $Memberships        = MembershipsHandler::getInstance();
        $Membership         = $this->getMembership();
        $membershipGroupIds = $Membership->getGroupIds();

        // remove user from unique group ids
        foreach ($Membership->getUniqueGroupIds() as $groupId) {
            $Groups->get($groupId)->removeUser($User);

            $k = array_search($groupId, $membershipGroupIds);

            if ($k !== false) {
                unset($membershipGroupIds[$k]);
            }
        }

        // remove user from all non-unique group ids where the user is not part of
        // the membership
        foreach ($membershipGroupIds as $groupId) {
            foreach ($Memberships->getMembershipIdsByGroupIds(array($groupId)) as $membershipId) {
                $OtherMembership = $Memberships->getChild($membershipId);

                if (!$OtherMembership->hasMembershipUserId($User->getId())) {
                    $User->removeGroup($groupId);
                }
            }
        }

        $User->save(QUI::getUsers()->getSystemUser());
    }

    /**
     * Archive this membership user
     *
     * @param string $reason - The reason why this user is archived
     * @return void
     */
    public function archive($reason)
    {
        $this->removeFromGroups();
        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_ARCHIVED, array(
            'reason' => $reason
        ));
        $this->setAttributes(array(
            'archived'      => 1,
            'archiveDate'   => Utils::getFormattedTimestamp(),
            'archiveReason' => $reason
        ));
        $this->update();
    }

    /**
     * Checks if this membership user is archived
     *
     * @retun bool
     */
    public function isArchived()
    {
        return boolval($this->getAttribute('archived'));
    }

    /**
     * Get the Membership this membership user is assigned to
     *
     * @return QUI\Memberships\Membership
     */
    public function getMembership()
    {
        return MembershipsHandler::getInstance()->getChild(
            $this->getAttribute('membershipId')
        );
    }

    /**
     * Get QUIQQER User ID of membership user
     *
     * @return int
     */
    public function getUserId()
    {
        return (int)$this->getAttribute('userId');
    }

    /**
     * Get QUIQQER User
     *
     * @return QUI\Users\User
     */
    public function getUser()
    {
        return QUI::getUsers()->get($this->getUserId());
    }

    /**
     * Add an entry to the membership user history
     *
     * @param string $type - History entry type (see \QUI\Memberships\Users\Handler)
     * @param string $msg (optional) - additional custom message
     */
    public function addHistoryEntry($type, $msg = "")
    {
        $history = $this->getAttribute('history');

        if (empty($history)) {
            $history = array();
        } else {
            $history = json_decode($history, true);
        }

        $User = QUI::getUserBySession();

        $history[] = array(
            'type' => $type,
            'time' => Utils::getFormattedTimestamp(),
            'user' => $User->getUsername() . ' (' . $User->getId() . ')',
            'msg'  => $msg
        );

        $this->setAttribute('history', json_encode($history));
    }

    /**
     * Get history data of this MembershipUser
     *
     * @return array
     */
    public function getHistory()
    {
        $history = $this->getAttribute('history');

        if (empty($history)) {
            $history = array();
        } else {
            $history = json_decode($history, true);
        }

        return $history;
    }

    /**
     * Format date based on User Locale and duration mode
     *
     * @param string $date - Formatted date YYYY-MM-DD HH:MM:SS
     * @return string
     */
    protected function formatDate($date)
    {
        $Locale       = $this->getUser()->getLocale();
        $durationMode = MembershipsHandler::getSetting('durationMode');
        $timestamp    = strtotime($date);

        switch ($durationMode) {
            case 'day':
                $dayDate       = date('Y-m-d', $timestamp);
                $formattedDate = $Locale->formatDate(strtotime($dayDate));
                break;

            default:
                $minuteDate    = date('Y-m-d H:i', $timestamp);
                $formattedDate = $Locale->formatDate(strtotime($minuteDate));
        }

        return $formattedDate;
    }

    /**
     * Get membership data for frontend view/edit purposes with correctly formatted dates
     *
     * @return array
     */
    public function getFrontendViewData()
    {
        $QuiqqerUser = $this->getUser();
        $Membership  = $this->getMembership();

        return array(
            'id'              => $this->getId(),
            'userId'          => $QuiqqerUser->getId(),
            'membershipId'    => $Membership->getId(),
            'membershipTitle' => $Membership->getTitle(),
            'username'        => $QuiqqerUser->getUsername(),
            'fullName'        => $QuiqqerUser->getName(),
            'addedDate'       => $this->formatDate($this->getAttribute('addedDate')),
            'beginDate'       => $this->formatDate($this->getAttribute('beginDate')),
            'endDate'         => $this->formatDate($this->getAttribute('endDate')),
            'archived'        => $this->isArchived(),
            'archiveReason'   => $this->getAttribute('archiveReason'),
            'cancelled'       => $this->isCancelled()
        );
    }

    /**
     * Get membership data for backend view/edit purposes
     *
     * @return array
     */
    public function getBackendViewData()
    {
        $QuiqqerUser = $this->getUser();
        $Membership  = $this->getMembership();

        return array(
            'id'              => $this->getId(),
            'userId'          => $QuiqqerUser->getId(),
            'membershipId'    => $Membership->getId(),
            'membershipTitle' => $Membership->getTitle(),
            'username'        => $QuiqqerUser->getUsername(),
            'fullName'        => $QuiqqerUser->getName(),
            'addedDate'       => $this->getAttribute('addedDate'),
            'beginDate'       => $this->getAttribute('beginDate'),
            'endDate'         => $this->getAttribute('endDate'),
            'archived'        => $this->isArchived(),
            'archiveReason'   => $this->getAttribute('archiveReason'),
            'cancelled'       => $this->isCancelled()
        );
    }

    /**
     * Send an email to the membership user
     *
     * @param string $subject - mail subject
     * @param string $templateFile
     * @param array $templateVars (optional) - additional template variables (besides $this)
     * @return void
     *
     * @throws QUI\Memberships\Exception
     */
    protected function sendMail($subject, $templateFile, $templateVars = array())
    {
        $User  = $this->getUser();
        $email = $User->getAttribute('email');

        if (empty($email)) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.membershipuser.no.email'
            ));
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign(array_merge(
            array(
                'MembershipUser' => $this,
                'Locale'         => $this->getUser()->getLocale(),
                'data'           => $this->getFrontendViewData()
            ),
            $templateVars
        ));

        $template = $Engine->fetch($templateFile);

        $Mailer = new Mailer();

        $Mailer->addRecipient($email, $User->getName());
        $Mailer->setSubject($subject);
        $Mailer->setBody($template);
        $Mailer->send();
    }
}
