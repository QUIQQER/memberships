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
     * Renew the current membership cycle of this membership user
     *
     * @return void
     */
    public function renew()
    {
        $Membership = $this->getMembership();
        $start      = time();
        $startDate  = Utils::getFormattedTimestamp($start);
        $endDate    = $Membership->calcEndDate($start);


    }

    /**
     * Start the membership cancellation process
     *
     * @return void
     */
    public function startCancel()
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
            'action' => 'confirmcancel'
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
        $Mailer = new Mailer();
        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign(array(
            'MembershipUser' => $this,
            'Membership'     => $this->getMembership(),
            'cancelDate'     => $cancelDate,
            'cancelUrl'      => $cancelUrl
        ));

        $template = $Engine->fetch(dirname(__FILE__, 5) . '/templates/mail_startcancel.html');

        // @todo E-Mail aus Benutzer holen
        $Mailer->addRecipient('peat@pcsg.de', $this->getUser()->getName());
        $Mailer->setSubject(
            QUI::getLocale()->get(
                'quiqqer/memberships',
                'templates.mail.startcancel.subject'
            )
        );
        $Mailer->setBody($template);
        $Mailer->send();
    }

    /**
     * Confirm membership cancellation
     *
     * @param string $confirmHash - cancel hash
     * @return void
     *
     * @throws QUI\Memberships\Exception
     */
    public function confirmCancel($confirmHash)
    {
        $cancelHash = $this->getAttribute('cancelHash');

        if (empty($cancelHash)) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.membershipuser.confirmCancel.no.hash'
            ));
        }

        if ($confirmHash !== $cancelHash) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.membershipuser.confirmCancel.hash.mismatch'
            ));
        }

        if ($this->isCancelled()) {
            throw new QUI\Memberships\Exception(array(
                'quiqqer/memberships',
                'exception.users.membershipuser.confirmCancel.already.cancelled'
            ));
        }

        $this->cancelMembership();
    }

    /**
     * Cancel the membership
     *
     * @return void
     */
    protected function cancelMembership()
    {
        $this->setAttributes(array(
            'cancelled' => true
        ));

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_CANCELLED);
        $this->archive();
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
        $this->removeFromGroups();
        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_DELETED);

        // do not delete, just set to archived
        $this->archive();
    }

    /**
     * Set User to all membership QUIQQER groups
     *
     * @return void
     */
    public function setToGroups()
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
     * @return void
     */
    protected function archive()
    {
        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_ARCHIVED);
        $this->setAttribute('archived', 1);
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
}
