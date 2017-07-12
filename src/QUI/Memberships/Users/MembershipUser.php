<?php

namespace QUI\Memberships\Users;

use QUI;
use QUI\CRUD\Child;
use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Utils;
use QUI\Mail\Mailer;
use QUI\Permissions\Permission;
use QUI\Verification\Verifier;

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
     * @param bool $withPermission - check permissions on update [default: true]
     */
    public function update($withPermission = true)
    {
        if ($withPermission !== false) {
            Permission::checkPermission(MembershipUsersHandler::PERMISSION_EDIT_USERS);
        }

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

        // Calculate new start and/or end time
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

        // send mail
        if ($auto) {
            $this->sendAutoExtendMail();
        } else {
            $this->sendManualExtendMail();
        }
    }

    /**
     * Send mail to the user if the membership is extended automatically
     *
     * @return void
     */
    protected function sendAutoExtendMail()
    {
        $sendMail = MembershipUsersHandler::getSetting('sendAutoExtendMail');

        if (!$sendMail) {
            return;
        }

        $subject = $this->getUser()->getLocale()->get(
            'quiqqer/memberships', 'templates.mail.autoextend.subject'
        );

        $this->sendMail($subject, dirname(__FILE__, 5) . '/templates/mail_autoextend.html');
    }

    /**
     * Send mail to the user if the membership is extended manually
     *
     * Manually = Either by admin edit or if the user is re-added to the membership
     * although he already is a member
     *
     * @return void
     */
    public function sendManualExtendMail()
    {
        $sendMail = MembershipUsersHandler::getSetting('sendManualExtendMail');

        if (!$sendMail) {
            return;
        }

        $subject = $this->getUser()->getLocale()->get(
            'quiqqer/memberships', 'templates.mail.manualextend.subject'
        );

        $this->sendMail($subject, dirname(__FILE__, 5) . '/templates/mail_manualextend.html');
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
     *
     * @throws QUI\Memberships\Exception
     */
    public function startManualCancel()
    {
        // check cancel permission
        if ((int)QUI::getUserBySession()->getId() !== (int)$this->getUserId()) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.membershipuser.manualcancel.no.permission'
            ));
        }

        if ($this->isCancelled()) {
            // @todo throw Exception?
            return;
        }

        $cancelDate = Utils::getFormattedTimestamp();

        $this->setAttributes(array(
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
                'cancelUrl'  => Verifier::startVerification($this->getCancelVerification(), true)
            )
        );
    }

    /**
     * Abort a manually stared cancellation process
     *
     * @return void
     * @throws QUI\Memberships\Exception
     */
    public function abortManualCancel()
    {
        // check cancel permission
        if ((int)QUI::getUserBySession()->getId() !== (int)$this->getUserId()) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.membershipuser.manualcancel.no.permission'
            ));
        }

        if (!$this->isCancelled()
            && empty($this->getAttribute('cancelDate'))
        ) {
            return;
        }

        $this->setAttributes(array(
            'cancelDate' => null,
            'cancelled'  => false
        ));

        Verifier::removeVerification($this->getCancelVerification());

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_CANCEL_ABORT);
        $this->update(false);
    }

    /**
     * Confirm membership cancellation
     *
     * @return void
     *
     * @throws QUI\Memberships\Exception
     */
    public function confirmManualCancel()
    {
        if ($this->isCancelled()) {
            return;
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

        if (empty($msg)) {
            $msg = "";
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
     * @return string|false - formatted date or false on error
     */
    protected function formatDate($date)
    {
        if (empty($date)
            || $date === '0000-00-00 00:00:00'
        ) {
            return false;
        }

        $Locale       = $this->getUser()->getLocale();
        $lang         = $Locale->getCurrent();
        $durationMode = MembershipsHandler::getSetting('durationMode');
        $Conf         = QUI::getPackage('quiqqer/memberships')->getConfig();

        switch ($durationMode) {
            case 'day':
                $dateFormat = $Conf->get('date_formats_short', $lang);

                // fallback to default value
                if (empty($dateFormat)) {
                    $dateFormat = '%D';
                }
                break;

            default:
                $dateFormat = $Conf->get('date_formats_long', $lang);

                // fallback to default value
                if (empty($dateFormat)) {
                    $dateFormat = '%D %H:%M';
                }
        }

        return $Locale->formatDate(strtotime($date), $dateFormat);
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
        $Locale      = $QuiqqerUser->getLocale();

        return array(
            'id'              => $this->getId(),
            'userId'          => $QuiqqerUser->getId(),
            'membershipId'    => $Membership->getId(),
            'membershipTitle' => $Membership->getTitle($Locale),
            'membershipShort' => $Membership->getDescription($Locale),
            'username'        => $QuiqqerUser->getUsername(),
            'fullName'        => $QuiqqerUser->getName(),
            'addedDate'       => $this->formatDate($this->getAttribute('addedDate')),
            'beginDate'       => $this->formatDate($this->getAttribute('beginDate')),
            'endDate'         => $this->formatDate($this->getAttribute('endDate')),
            'cancelDate'      => $this->formatDate($this->getAttribute('cancelDate')),
//            'archived'        => $this->isArchived(),
//            'archiveReason'   => $this->getAttribute('archiveReason'),
            'cancelled'       => $this->isCancelled(),
            'autoExtend'      => $Membership->isAutoExtend()
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
            'firstname'       => $QuiqqerUser->getAttribute('firstname'),
            'lastname'        => $QuiqqerUser->getAttribute('lastname'),
            'fullName'        => $QuiqqerUser->getName(),
            'addedDate'       => $this->getAttribute('addedDate'),
            'beginDate'       => $this->getAttribute('beginDate'),
            'endDate'         => $this->getAttribute('endDate'),
            'archived'        => $this->isArchived(),
            'archiveReason'   => $this->getAttribute('archiveReason'),
            'archiveDate'     => $this->getAttribute('archiveDate'),
            'cancelled'       => $this->isCancelled()
        );
    }

    /**
     * Get Verification object for MembershipUser cancellation
     *
     * @return CancelVerification
     */
    protected function getCancelVerification()
    {
        return new CancelVerification($this->id);
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