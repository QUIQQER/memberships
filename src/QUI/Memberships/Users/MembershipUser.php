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
use QUI\ERP\Products\Handler\Products as ProductsHandler;
use QUI\ERP\Accounting\Contracts\Handler as ContractsHandler;
use QUI\Interfaces\Users\User as QUIUserInterface;

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
     * User that is editing this MembershipUser in the current runtime
     *
     * @var QUIUserInterface
     */
    protected $EditUser = null;

    /**
     * Set User that is editing this MembershipUser in the current runtime
     *
     * @param QUIUserInterface $EditUser
     */
    public function setEditUser(QUIUserInterface $EditUser)
    {
        $this->EditUser = $EditUser;
    }

    /**
     * @inheritdoc
     * @param bool $withPermission - check permissions on update [default: true]
     */
    public function update()
    {
        Permission::checkPermission(MembershipUsersHandler::PERMISSION_EDIT_USERS, $this->EditUser);

        // check certain attributes
        if (!$this->getMembership()->isInfinite()) {
            $beginDate = strtotime($this->getAttribute('beginDate'));
            $endDate   = strtotime($this->getAttribute('endDate'));

            if ($beginDate === false
                || $endDate === false
            ) {
                throw new QUI\Memberships\Exception([
                    'quiqqer/memberships',
                    'exception.users.membershipuser.wrong.dates'
                ]);
            }

            if ($beginDate >= $endDate) {
                throw new QUI\Memberships\Exception([
                    'quiqqer/memberships',
                    'exception.users.membershipuser.begin.after.end'
                ]);
            }
        }

        // check dates
        foreach ($this->getAttributes() as $k => $v) {
            switch ($k) {
                case 'addedDate':
                case 'beginDate':
                case 'endDate':
                case 'cancelDate':
                case 'archiveDate':
                    if (empty($v) || $v === '0000-00-00 00:00:00') {
                        $this->setAttribute($k, null);
                    }
                    break;

                case 'cancelled':
                    $this->setAttribute($k, $v ? 1 : 0);
                    break;
            }
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

            $this->setAttributes([
                'beginDate'     => Utils::getFormattedTimestamp($start),
                'endDate'       => $Membership->calcEndDate($start),
                'extendCounter' => $extendCounter + 1
            ]);
        } else {
            $endDate = $this->getAttribute('endDate');

            $this->setAttributes([
                'endDate' => $Membership->calcEndDate(strtotime($endDate))
            ]);
        }

        $historyData = [
            'start' => $this->getAttribute('beginDate'),
            'end'   => $this->getAttribute('endDate'),
            'auto'  => $auto ? '1' : '0'
        ];

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
            'quiqqer/memberships',
            'templates.mail.autoextend.subject'
        );

        $this->sendMail($subject, dirname(__FILE__, 5).'/templates/mail_autoextend.html');
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
            'quiqqer/memberships',
            'templates.mail.manualextend.subject'
        );

        $this->sendMail($subject, dirname(__FILE__, 5).'/templates/mail_manualextend.html');
    }

    /**
     * Expires this memberships user
     *
     * @return void
     * @throws \QUI\Exception
     */
    public function expire()
    {
        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_EXPIRED);
        $this->archive(MembershipUsersHandler::ARCHIVE_REASON_EXPIRED);

        // send expire mail
        $subject = $this->getUser()->getLocale()->get('quiqqer/memberships', 'templates.mail.expired.subject');
        $this->sendMail($subject, dirname(__FILE__, 5).'/templates/mail_expired.html');

        QUI::getEvents()->fireEvent('quiqqerMembershipsExpired', [$this]);
    }

    /**
     * Start the manual membership cancellation process
     *
     * Generates a random hash and sends an email to the user
     *
     * @return void
     *
     * @throws QUI\Memberships\Exception
     * @throws QUI\Verification\Exception
     * @throws QUI\Exception
     */
    public function startManualCancel()
    {
        // check cancel permission
        if ((int)QUI::getUserBySession()->getId() !== (int)$this->getUserId()) {
            throw new QUI\Memberships\Exception([
                'quiqqer/memberships',
                'exception.users.membershipuser.manualcancel.no.permission'
            ]);
        }

        if ($this->isCancelled()) {
            return;
        }

        $Membership = $this->getMembership();

        // cannot manually cancel infinite memberships
        if ($Membership->isInfinite()) {
            return;
        }

        // cannot manually cancel default membership
        if ($Membership->isDefault()) {
            return;
        }

        $userEmail = $this->getUser()->getAttribute('email');

        if (empty($userEmail)) {
            throw new QUI\Memberships\Exception([
                'quiqqer/memberships',
                'exception.users.membershipuser.manualcancel.no_email_address'
            ]);
        }

        $cancelUrl  = Verifier::startVerification($this->getCancelVerification(), true);
        $cancelDate = Utils::getFormattedTimestamp();

        $this->setAttributes([
            'cancelStatus' => MembershipUsersHandler::CANCEL_STATUS_CANCEL_CONFIRM_PENDING,
            'cancelDate'   => $cancelDate
        ]);

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_CANCEL_START);

        // save cancel hash and date to database
        $this->setEditUser(QUI::getUsers()->getSystemUser());
        $this->update();

        // send cancellation mail
        $this->sendMail(
            QUI::getLocale()->get('quiqqer/memberships', 'templates.mail.startcancel.subject'),
            dirname(__FILE__, 5).'/templates/mail_startcancel.html',
            [
                'cancelDate'    => $cancelDate,
                'cancelUrl'     => $cancelUrl,
                'cancelEndDate' => $this->getCurrentCancelEndDate()->format('Y-m-d H:i:s')
            ]
        );
    }

    /**
     * Start to abort a manually stared cancellation process
     *
     * @return void
     * @throws QUI\Memberships\Exception
     * @throws QUI\Verification\Exception
     * @throws QUI\Exception
     */
    public function startAbortCancel()
    {
        // check cancel permission
        if ((int)QUI::getUserBySession()->getId() !== (int)$this->getUserId()) {
            throw new QUI\Memberships\Exception([
                'quiqqer/memberships',
                'exception.users.membershipuser.manualcancel.no.permission'
            ]);
        }

        $cancelStatus = (int)$this->getAttribute('cancelStatus');

        if ($cancelStatus !== MembershipUsersHandler::CANCEL_STATUS_CANCEL_CONFIRM_PENDING
            && $cancelStatus !== MembershipUsersHandler::CANCEL_STATUS_CANCELLED
        ) {
            return;
        }

        $userEmail = $this->getUser()->getAttribute('email');

        if (empty($userEmail)) {
            throw new QUI\Memberships\Exception([
                'quiqqer/memberships',
                'exception.users.membershipuser.abortcancel.no_email_address'
            ]);
        }

        $abortCancelUrl = Verifier::startVerification($this->getAbortCancelVerification(), true);

        $this->setAttributes([
            'cancelStatus' => MembershipUsersHandler::CANCEL_STATUS_ABORT_CANCEL_CONFIRM_PENDING,
        ]);

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_CANCEL_ABORT_START);
        $this->setEditUser(QUI::getUsers()->getSystemUser());
        $this->update();

        // send abort cancellation mail
        $this->sendMail(
            QUI::getLocale()->get('quiqqer/memberships', 'templates.mail.startabortcancel.subject'),
            dirname(__FILE__, 5).'/templates/mail_startabortcancel.html',
            [
                'abortCancelUrl' => $abortCancelUrl
            ]
        );
    }

    /**
     * Confirm abortion of cancellation
     *
     * @return void
     */
    public function confirmAbortCancel()
    {
        $this->setAttributes([
            'cancelDate'    => null,
            'cancelStatus'  => MembershipUsersHandler::CANCEL_STATUS_NOT_CANCELLED,
            'cancelled'     => false,
            'cancelEndDate' => null
        ]);

        Verifier::removeVerification($this->getAbortCancelVerification());

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_CANCEL_ABORT_CONFIRM);
        $this->setEditUser(QUI::getUsers()->getSystemUser());
        $this->update();

        QUI::getEvents()->fireEvent('quiqqerMembershipsCancelAbort', [$this]);
    }

    /**
     * Confirm membership cancellation by user
     *
     * @return void
     * @throws QUI\Memberships\Exception
     * @throws QUI\ExceptionStack|QUI\Exception
     */
    public function confirmManualCancel()
    {
        if ($this->isCancelled()) {
            return;
        }

        $this->setAttributes([
            'cancelled'     => true,
            'cancelStatus'  => MembershipUsersHandler::CANCEL_STATUS_CANCELLED,
            'cancelEndDate' => $this->getCurrentCancelEndDate()->format('Y-m-d H:i:s')
        ]);

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_CANCEL_CONFIRM);
        $this->update();

        // send confirm cancel mail
        $this->sendConfirmCancelMail();

        QUI::getEvents()->fireEvent('quiqqerMembershipsCancelConfirm', [$this]);
    }

    /**
     * Send mail to user to confirm cancellation
     *
     * @return void
     */
    public function sendConfirmCancelMail()
    {
        $subject = $this->getUser()->getLocale()->get('quiqqer/memberships', 'templates.mail.confirmcancel.subject');
        $this->sendMail($subject, dirname(__FILE__, 5).'/templates/mail_confirmcancel.html');
    }

    /**
     * Cancel membership
     *
     * @return void
     * @throws \QUI\Exception
     */
    public function cancel()
    {
        $this->archive(MembershipUsersHandler::ARCHIVE_REASON_CANCELLED);

        // send expired mail
        $subject = $this->getUser()->getLocale()->get('quiqqer/memberships', 'templates.mail.expired.subject');
        $this->sendMail($subject, dirname(__FILE__, 5).'/templates/mail_cancelled.html');

        QUI::getEvents()->fireEvent('quiqqerMembershipsCancelled', [$this]);
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
        Permission::checkPermission(MembershipUsersHandler::PERMISSION_EDIT_USERS, $this->EditUser);

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_DELETED);

        // do not delete, just set to archived
        $this->archive(MembershipUsersHandler::ARCHIVE_REASON_DELETED);

        QUI::getEvents()->fireEvent('quiqqerMembershipsUserDelete', [$this]);
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
            foreach ($Memberships->getMembershipIdsByGroupIds([$groupId]) as $membershipId) {
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
        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_ARCHIVED, [
            'reason' => $reason
        ]);
        $this->setAttributes([
            'archived'      => 1,
            'archiveDate'   => Utils::getFormattedTimestamp(),
            'archiveReason' => $reason
        ]);
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
     * @throws \QUI\Exception
     */
    public function getUser()
    {
        return QUI::getUsers()->get($this->getUserId());
    }

    /**
     * Get ID of the Contract if this MembershipUser was created due to a
     * contract.
     *
     * @return int|false
     */
    public function getContractId()
    {
        $contractId = $this->getAttribute('contractId');

        if (empty($contractId)) {
            return false;
        }

        return (int)$contractId;
    }

    /**
     * Add an entry to the membership user history
     *
     * @param string $type - History entry type (see \QUI\Memberships\Users\Handler)
     * @param string $msg (optional) - additional custom message
     */
    public function addHistoryEntry($type, $msg = "")
    {
        $history = $this->getHistory();

        if (empty($msg)) {
            $msg = "";
        }

        if (is_array($msg)) {
            $msg = json_encode($msg);
        }

        $User = QUI::getUserBySession();

        $history[] = [
            'type' => $type,
            'time' => Utils::getFormattedTimestamp(),
            'user' => $User->getName().' ('.$User->getId().')',
            'msg'  => $msg
        ];

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
            $history = [];
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
     * @throws \QUI\Exception
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
        $Locale      = QUI::getLocale();

        // determine source of title, short and content
        $viewDataMode = MembershipUsersHandler::getSetting('viewDataMode');
        $productId    = $this->getAttribute('productId');

        if ($viewDataMode === 'product'
            && !empty($productId)
            && Utils::isQuiqqerProductsInstalled()
        ) {
            $Product     = ProductsHandler::getProduct($productId);
            $title       = $Product->getTitle($Locale);
            $description = $Product->getDescription($Locale);
            $content     = '';
        } else {
            $title       = $Membership->getTitle($Locale);
            $description = $Membership->getDescription($Locale);
            $content     = $Membership->getContent($Locale);
        }

        return [
            'id'                => $this->getId(),
            'userId'            => $QuiqqerUser->getId(),
            'membershipId'      => $Membership->getId(),
            'membershipTitle'   => $title,
            'membershipShort'   => $description,
            'membershipContent' => $content,
            'username'          => $QuiqqerUser->getUsername(),
            'fullName'          => $QuiqqerUser->getName(),
            'addedDate'         => $this->formatDate($this->getAttribute('addedDate')),
            'beginDate'         => $this->formatDate($this->getAttribute('beginDate')),
            'endDate'           => $this->formatDate($this->getAttribute('endDate')),
            'cancelEndDate'     => $this->formatDate($this->getCurrentCancelEndDate()->format('Y-m-d H:i:s')),
            'cancelDate'        => $this->formatDate($this->getAttribute('cancelDate')),
            'cancelStatus'      => $this->getAttribute('cancelStatus'),
//            'archived'        => $this->isArchived(),
//            'archiveReason'   => $this->getAttribute('archiveReason'),
            'cancelled'         => $this->isCancelled(),
            'autoExtend'        => $Membership->isAutoExtend(),
            'infinite'          => $Membership->isInfinite()
        ];
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

        return [
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
            'cancelled'       => $this->isCancelled(),
            'extraData'       => $this->getExtraData(),
            'infinite'        => $Membership->isInfinite(),
            'contractId'      => $this->getContractId()
        ];
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
     * Get Verification object for MembershipUser cancel abort
     *
     * @return AbortCancelVerification
     */
    protected function getAbortCancelVerification()
    {
        return new AbortCancelVerification($this->id);
    }

    /**
     * Send an email to the membership user
     *
     * @param string $subject - mail subject
     * @param string $templateFile
     * @param array $templateVars (optional) - additional template variables (besides $this)
     * @return void
     *
     * @throws \QUI\Exception
     */
    protected function sendMail($subject, $templateFile, $templateVars = [])
    {
        $User  = $this->getUser();
        $email = $User->getAttribute('email');

        if (empty($email)) {
            QUI\System\Log::addError(
                'Could not send mail to user #'.$User->getId().' because the user has'
                .' no email address!'
            );
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign(array_merge(
            [
                'MembershipUser' => $this,
                'Locale'         => $this->getUser()->getLocale(),
                'data'           => $this->getFrontendViewData()
            ],
            $templateVars
        ));

        $template = $Engine->fetch($templateFile);

        $Mailer = new Mailer();

        $Mailer->addRecipient($email, $User->getName());
        $Mailer->setSubject($subject);
        $Mailer->setBody($template);
        $Mailer->send();
    }

    /**
     * Set any extra text data to the MembershipUser
     *
     * This is meant for extra information that is not already covered by the history.
     *
     * @param string $key
     * @param string $value
     */
    public function setExtraData($key, $value)
    {
        $extraData = $this->getExtraData();

        $User       = QUI::getUserBySession();
        $userString = $User->getName().' ('.$User->getId().')';
        $editString = Utils::getFormattedTimestamp().' - '.$userString;

        if (isset($extraData[$key])) {
            $extraData[$key]['edit']  = $editString;
            $extraData[$key]['value'] = $value;
        } else {
            $extraData[$key] = [
                'value' => $value,
                'add'   => $editString,
                'edit'  => '-'
            ];
        }

        $this->setAttribute('extraData', json_encode($extraData));
    }

    /**
     * Get extra data of this MembershipUser
     *
     * @param string $key (optional) - If omitted return all extra data
     * @return array|string|false
     */
    public function getExtraData($key = null)
    {
        $extraData = $this->getAttribute('extraData');

        if (empty($extraData)) {
            $extraData = [];
        } else {
            $extraData = json_decode($extraData, true);
        }

        if (is_null($key)) {
            return $extraData;
        }

        if (!array_key_exists($key, $extraData)) {
            return false;
        }

        return $extraData[$key]['value'];
    }

    /**
     * Calculates the date the membership for this user would end
     * if it was cancelled NOW
     *
     * @return \DateTime
     * @throws \Exception
     */
    public function getCurrentCancelEndDate()
    {
        $endDate    = $this->getAttribute('endDate');
        $EndDate    = new \DateTime($endDate);
        $contractId = $this->getContractId();

        if (empty($contractId)) {
            return $EndDate;
        }

        /**
         * If a contract is connected to this MembershipUser
         * the period of notice of this contract has to be considered
         * when cancelling the membership.
         */
        $Contract = ContractsHandler::getInstance()->get($contractId);

        if ($Contract->isInPeriodOfNotice()) {
            return $EndDate;
        }

        $actualEndTime = $this->getMembership()->calcEndDate($EndDate->getTimestamp());
        return new \DateTime($actualEndTime);
    }
}
