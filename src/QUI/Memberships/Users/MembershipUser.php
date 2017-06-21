<?php

namespace QUI\Memberships\Users;

use QUI;
use QUI\CRUD\Child;
use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Utils;
use QUI\Mail\Mailer;

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
     * Extend the current membership cycle of this membership user
     *
     * @param bool $auto (optional) - Used if the membership is automatically extended.
     * If set to false, the setting membershipusers.extendMode is used [default: true]
     * @return void
     */
    public function extend($auto = true)
    {
        $Membership = $this->getMembership();
        $extendMode = MembershipUsersHandler::getConfigEntry('extendMode');

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

        $historyEntry = 'start: ' . $this->getAttribute('beginDate');
        $historyEntry .= ' | end: ' . $this->getAttribute('endDate');
        $historyEntry .= ' | auto: ';
        $auto ? $historyEntry .= '1' : $historyEntry .= '0';

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_EXTENDED, $historyEntry);
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

        if ($this->isCancelled()) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.membershipuser.confirmManualCancel.already.cancelled'
            ));
        }

        $this->setAttributes(array(
            'cancelled' => true
        ));

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_CANCEL_CONFIRM);

        // send confirm cancel mail
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
        return $this->getAttribute('cancelled');
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
        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_ARCHIVED);
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
    public function addHistoryEntry($type, $msg = null)
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
            'msg'  => $msg ?: '-'
        );

        $this->setAttribute('history', json_encode($history));
    }

    /**
     * Send an email to the membership user
     *
     * @param string $subject - mail subject
     * @param string $templateFile
     * @param array $templateVars (optional) - additional template variables (besides $this)
     * @return void
     */
    protected function sendMail($subject, $templateFile, $templateVars = array())
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign(array_merge(
            array(
                'MembershipUser' => $this,
                'Locale'         => $this->getUser()->getLocale()
            ),
            $templateVars
        ));

        $template = $Engine->fetch($templateFile);

        $Mailer = new Mailer();

        // @todo E-Mail aus Benutzer holen
        $Mailer->addRecipient('peat@pcsg.de', $this->getUser()->getName());
        $Mailer->setSubject($subject);
        $Mailer->setBody($template);
        $Mailer->send();
    }
}
