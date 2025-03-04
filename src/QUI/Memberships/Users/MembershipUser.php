<?php

namespace QUI\Memberships\Users;

use DateInterval;
use DateTime;
use QUI;
use QUI\CRUD\Child;
use QUI\ERP\Accounting\Contracts\Handler as ContractsHandler;
use QUI\ERP\Products\Handler\Products as ProductsHandler;
use QUI\Exception;
use QUI\ExceptionStack;
use QUI\Interfaces\Users\User as QUIUserInterface;
use QUI\Mail\Mailer;
use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Users\Handler as MembershipUsersHandler;
use QUI\Memberships\Utils;
use QUI\Permissions\Permission;
use QUI\Verification\Verifier;

use function date_create;
use function date_interval_create_from_date_string;

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
     * The Membership this MembershipUser is assigned to
     *
     * @var ?QUI\Memberships\Membership
     */
    protected ?QUI\Memberships\Membership $Membership = null;

    /**
     * User that is editing this MembershipUser in the current runtime
     *
     * @var ?QUIUserInterface
     */
    protected ?QUIUserInterface $EditUser = null;

    /**
     * Set User that is editing this MembershipUser in the current runtime
     *
     * @param QUIUserInterface $EditUser
     */
    public function setEditUser(QUIUserInterface $EditUser): void
    {
        $this->EditUser = $EditUser;
    }

    public function update(): void
    {
        Permission::checkPermission(MembershipUsersHandler::PERMISSION_EDIT_USERS, $this->EditUser);

        // check certain attributes
        if (!$this->getMembership()->isInfinite()) {
            $beginDate = strtotime($this->getAttribute('beginDate'));
            $endDate = strtotime($this->getAttribute('endDate'));

            if (
                $beginDate === false
                || $endDate === false
            ) {
                throw new QUI\Memberships\Exception([
                    'quiqqer/memberships',
                    'exception.users.membershipuser.wrong.dates',
                    [
                        'id' => $this->getId()
                    ]
                ]);
            }

            if ($beginDate >= $endDate) {
                throw new QUI\Memberships\Exception([
                    'quiqqer/memberships',
                    'exception.users.membershipuser.begin.after.end',
                    [
                        'id' => $this->getId()
                    ]
                ]);
            }
        }

        // check dates
        foreach ($this->getAttributes() as $k => $v) {
            switch ($k) {
                case 'beginDate':
                case 'endDate':
                case 'addedDate':
                case 'cancelDate':
                case 'archiveDate':
                    if (empty($v) || $v === '0000-00-00 00:00:00') {
                        $this->setAttribute($k, null);
                    } else {
                        $this->setAttribute($k, Utils::getFormattedTimestamp($v));
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
     * @param DateTime|null $NextBeginDate (optional) - New cycle begin date
     * @param DateTime|null $NextEndDate (optional) - New cycle end date
     * @return void
     * @throws Exception
     * @throws ExceptionStack
     */
    public function extend(
        bool $auto = true,
        null | DateTime $NextBeginDate = null,
        null | DateTime $NextEndDate = null
    ): void {
        // Calculate new start and/or end time
        if (empty($NextBeginDate)) {
            if (MembershipUsersHandler::getExtendMode() === MembershipUsersHandler::EXTEND_MODE_PROLONG) {
                $NextBeginDate = $this->getCycleBeginDate();
            } else {
                $NextBeginDate = $this->getNextCycleBeginDate();
            }
        }

        if (empty($NextEndDate)) {
            $NextEndDate = $this->getNextCycleEndDate();
        }

        if ($auto) {
            $extendCounter = $this->getAttribute('extendCounter');

            $this->setAttributes([
                'beginDate' => Utils::getFormattedTimestamp($NextBeginDate),
                'endDate' => Utils::getFormattedTimestamp($NextEndDate),
                'extendCounter' => $extendCounter + 1
            ]);
        } else {
            $this->setAttributes([
                'endDate' => Utils::getFormattedTimestamp($NextEndDate)
            ]);
        }

        $historyData = [
            'start' => Utils::getFormattedTimestamp($NextBeginDate),
            'end' => Utils::getFormattedTimestamp($NextEndDate),
            'auto' => $auto ? '1' : '0'
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
     * Calculate the end date of the current cycle based on a start date
     *
     * @param DateTime|null $Start (optional) - Calculate based on this start date [default: now]
     * @return DateTime
     * @throws Exception
     */
    public function calcEndDate(null | DateTime $Start = null): DateTime
    {
        if (empty($Start)) {
            $Start = date_create();
        }

        $contractId = $this->getContractId();
        $NewEndDate = date_create($this->getMembership()->calcEndDate($Start->getTimestamp()));

        if (empty($contractId)) {
            return $NewEndDate;
        }

        try {
            $Contract = ContractsHandler::getInstance()->getContract($contractId);
            $ContractExtensionInterval = $Contract->getExtensionInterval();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return $NewEndDate;
        }

        if (!$ContractExtensionInterval) {
            return $NewEndDate;
        }

        $NewEndDate = $Start->add($ContractExtensionInterval);

        if (MembershipUsersHandler::getDurationMode() == MembershipUsersHandler::DURATION_MODE_DAY) {
            $NewEndDate->add(new DateInterval('P1D'));
            $NewEndDate->setTime(23, 59, 59);
        }

        return $NewEndDate;
    }

    /**
     * Send mail to the user if the membership is extended automatically
     *
     * @return void
     * @throws Exception
     */
    protected function sendAutoExtendMail(): void
    {
        $sendMail = MembershipUsersHandler::getSetting('sendAutoExtendMail');

        if (!$sendMail) {
            return;
        }

        try {
            $subject = $this->getUser()->getLocale()->get(
                'quiqqer/memberships',
                'templates.mail.autoextend.subject'
            );

            $this->sendMail($subject, dirname(__FILE__, 5) . '/templates/mail_autoextend.html');
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Send mail to the user if the membership is extended manually
     *
     * Manually = Either by admin edit or if the user is re-added to the membership,
     * although he already is a member
     *
     * @return void
     * @throws Exception
     */
    public function sendManualExtendMail(): void
    {
        $sendMail = MembershipUsersHandler::getSetting('sendManualExtendMail');

        if (!$sendMail) {
            return;
        }

        try {
            $subject = $this->getUser()->getLocale()->get(
                'quiqqer/memberships',
                'templates.mail.manualextend.subject'
            );

            $this->sendMail($subject, dirname(__FILE__, 5) . '/templates/mail_manualextend.html');
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Expires this memberships user
     *
     * @return void
     * @throws Exception
     */
    public function expire(): void
    {
        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_EXPIRED);
        $this->archive(MembershipUsersHandler::ARCHIVE_REASON_EXPIRED);

        // send expire mail
        $subject = $this->getUser()->getLocale()->get('quiqqer/memberships', 'templates.mail.expired.subject');
        $this->sendMail($subject, dirname(__FILE__, 5) . '/templates/mail_expired.html');

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
     * @throws Exception
     */
    public function startManualCancel(): void
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

        $cancelUrl = Verifier::startVerification($this->getCancelVerification(), true);
        $cancelDate = Utils::getFormattedTimestamp();
        $CancelEndDate = $this->getCurrentCancelEndDate();

        $this->setAttributes([
            'cancelStatus' => MembershipUsersHandler::CANCEL_STATUS_CANCEL_CONFIRM_PENDING,
            'cancelDate' => $cancelDate,
            'cancelEndDate' => $CancelEndDate->format('Y-m-d H:i:s')
        ]);

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_CANCEL_START);

        // save cancel hash and date to database
        $this->setEditUser(QUI::getUsers()->getSystemUser());
        $this->update();

        // send cancellation mail
        $User = $this->getUser();

        $this->sendMail(
            QUI::getLocale()->get('quiqqer/memberships', 'templates.mail.startcancel.subject'),
            dirname(__FILE__, 5) . '/templates/mail_startcancel.html',
            [
                'cancelDate' => $cancelDate,
                'cancelUrl' => $cancelUrl,
                'cancelEndDate' => $User->getLocale()->formatDate($CancelEndDate->getTimestamp())
            ]
        );
    }

    /**
     * Automatic cancellation of a MembershipUser.
     *
     * HINT: This is not supposed to be executed by the user, but programmatically only if
     * a membership needs to be cancelled for other reasons than a manual cancellation by the user.
     *
     * A user CANNOT revoke a cancellation executed this way!
     *
     * @return void
     * @throws \Exception
     */
    public function autoCancel(): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $Membership = $this->getMembership();

        // cannot cancel infinite memberships
        if ($Membership->isInfinite()) {
            return;
        }

        // cannot cancel default membership
        if ($Membership->isDefault()) {
            return;
        }

        $cancelDate = Utils::getFormattedTimestamp();

        $this->setAttributes([
            'cancelStatus' => MembershipUsersHandler::CANCEL_STATUS_CANCELLED_BY_SYSTEM,
            'cancelDate' => $cancelDate,
            'cancelled' => true
        ]);

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_CANCEL_START_SYSTEM);

        // save cancel hash and date to database
        $this->setEditUser(QUI::getUsers()->getSystemUser());

        try {
            $this->update();
            QUI::getEvents()->fireEvent('quiqqerMembershipsAutoCancel', [$this]);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Start to abort a manually started cancellation process
     *
     * @return void
     * @throws QUI\Memberships\Exception
     * @throws QUI\Verification\Exception
     * @throws Exception
     */
    public function startAbortCancel(): void
    {
        // check cancel permission
        if ((int)QUI::getUserBySession()->getId() !== (int)$this->getUserId()) {
            throw new QUI\Memberships\Exception([
                'quiqqer/memberships',
                'exception.users.membershipuser.manualcancel.no.permission'
            ]);
        }

        $cancelStatus = (int)$this->getAttribute('cancelStatus');

        // If cancellation was initiated programmatically (by system), a user cannot undo this
        if ($cancelStatus === MembershipUsersHandler::CANCEL_STATUS_CANCELLED_BY_SYSTEM) {
            throw new QUI\Memberships\Exception([
                'quiqqer/memberships',
                'exception.users.membershipuser.manualcancel.no_system_uncancel'
            ]);
        }

        if (
            $cancelStatus !== MembershipUsersHandler::CANCEL_STATUS_CANCEL_CONFIRM_PENDING
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
            dirname(__FILE__, 5) . '/templates/mail_startabortcancel.html',
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
    public function confirmAbortCancel(): void
    {
        $this->setAttributes([
            'cancelDate' => null,
            'cancelStatus' => MembershipUsersHandler::CANCEL_STATUS_NOT_CANCELLED,
            'cancelled' => false,
            'cancelEndDate' => null
        ]);

        try {
            Verifier::removeVerification($this->getAbortCancelVerification());
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_CANCEL_ABORT_CONFIRM);
        $this->setEditUser(QUI::getUsers()->getSystemUser());

        try {
            $this->update();
            QUI::getEvents()->fireEvent('quiqqerMembershipsCancelAbort', [$this]);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Confirm membership cancellation by user
     *
     * @return void
     * @throws QUI\Memberships\Exception
     * @throws QUI\ExceptionStack|Exception
     */
    public function confirmManualCancel(): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $this->setAttributes([
            'cancelled' => true,
            'cancelStatus' => MembershipUsersHandler::CANCEL_STATUS_CANCELLED,
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
    public function sendConfirmCancelMail(): void
    {
        try {
            $subject = $this->getUser()->getLocale()->get(
                'quiqqer/memberships',
                'templates.mail.confirmcancel.subject'
            );

            $this->sendMail($subject, dirname(__FILE__, 5) . '/templates/mail_confirmcancel.html');
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Send e-mail to remind user of an outstanding cancellation confirmation.
     *
     * @return bool - success
     */
    public function sendConfirmCancelReminderMail(): bool
    {
        try {
            $subject = $this->getUser()->getLocale()->get(
                'quiqqer/memberships',
                'templates.mail.confirmcancel_reminder.subject'
            );

            $this->sendMail(
                $subject,
                dirname(__FILE__, 5) . '/templates/mail_confirmcancel_reminder.html',
                [
                    'cancelUrl' => Verifier::getVerificationUrl($this->getCancelVerification())
                ]
            );

            $this->addHistoryEntry(
                Handler::HISTORY_TYPE_MISC,
                QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'history.MembershipUser.cancel_confirm_reminder_sent'
                )
            );

            $this->EditUser = QUI::getUsers()->getSystemUser();
            $this->update();

            return true;
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }
    }

    /**
     * Cancel membership
     *
     * @return void
     * @throws Exception
     */
    public function cancel(): void
    {
        $this->archive(MembershipUsersHandler::ARCHIVE_REASON_CANCELLED);

        // send expired mail
        $subject = $this->getUser()->getLocale()->get('quiqqer/memberships', 'templates.mail.expired.subject');
        $this->sendMail($subject, dirname(__FILE__, 5) . '/templates/mail_cancelled.html');

        QUI::getEvents()->fireEvent('quiqqerMembershipsCancelled', [$this]);
    }

    /**
     * Check if this user has cancelled his membership
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return boolval($this->getAttribute('cancelled'));
    }

    /**
     * Delete membership user and remove QUIQQER user from all unique groups
     *
     * A deleted membership user is not removed from the database but set to "archived".
     *
     * @return void
     * @throws QUI\Permissions\Exception|ExceptionStack|Exception
     */
    public function delete(): void
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
     * @throws Exception
     */
    public function addToGroups(): void
    {
        $groupIds = $this->getMembership()->getGroupIds();
        $User = $this->getUser();

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
     * @throws Exception
     */
    protected function removeFromGroups(): void
    {
        /**
         * Check if the user exists first. If he does NOT, then he does not need to be removed
         * from QUIQQER groups (anymore).
         */
        try {
            $User = QUI::getUsers()->get($this->getUserId());
        } catch (\Exception $Exception) {
            if ($Exception->getCode() === 404) {
                return;
            }

            QUI\System\Log::writeException($Exception);
            return;
        }

        $Groups = QUI::getGroups();
        $Memberships = MembershipsHandler::getInstance();
        $Membership = $this->getMembership();
        $membershipGroupIds = $Membership->getGroupIds();

        // remove user from unique group ids
        foreach ($Membership->getUniqueGroupIds() as $groupId) {
            if ($User instanceof QUI\Users\User) {
                $Groups->get($groupId)->removeUser($User);
            }

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

                if (
                    method_exists($OtherMembership, 'hasMembershipUserId')
                    && !$OtherMembership->hasMembershipUserId($User->getId())
                ) {
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
     * @throws Exception
     */
    public function archive(string $reason): void
    {
        $this->removeFromGroups();
        $this->addHistoryEntry(MembershipUsersHandler::HISTORY_TYPE_ARCHIVED, $reason);
        $this->setAttributes([
            'archived' => 1,
            'archiveDate' => Utils::getFormattedTimestamp(),
            'archiveReason' => $reason
        ]);
        $this->update();
    }

    /**
     * Checks if this membership user is archived
     *
     * @retun bool
     */
    public function isArchived(): bool
    {
        return boolval($this->getAttribute('archived'));
    }

    /**
     * Get the Membership this membership user is assigned to
     *
     * @return QUI\Memberships\Membership|null
     * @throws Exception
     */
    public function getMembership(): ?QUI\Memberships\Membership
    {
        if ($this->Membership) {
            return $this->Membership;
        }

        // @phpstan-ignore-next-line
        $this->Membership = MembershipsHandler::getInstance()->getChild(
            $this->getAttribute('membershipId')
        );

        // @phpstan-ignore-next-line
        return $this->Membership;
    }

    /**
     * Get QUIQQER User ID of membership user
     *
     * @return int|string
     */
    public function getUserId(): int | string
    {
        return $this->getAttribute('userId');
    }

    /**
     * Get QUIQQER User
     *
     * @return QUIUserInterface|null
     */
    public function getUser(): ?QUIUserInterface
    {
        try {
            return QUI::getUsers()->get($this->getUserId());
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return null;
        }
    }

    /**
     * Get ID of the Contract if this MembershipUser was created due to a
     * contract.
     *
     * @return int|false
     */
    public function getContractId(): false | int
    {
        $contractId = $this->getAttribute('contractId');

        if (empty($contractId)) {
            return false;
        }

        return (int)$contractId;
    }

    /**
     * Get contract that is currently associated to this MembershipUser
     *
     * @return false|QUI\ERP\Accounting\Contracts\Contract
     */
    public function getContract(): QUI\ERP\Accounting\Contracts\Contract | bool
    {
        $contractId = $this->getContractId();

        if (!$contractId) {
            return false;
        }

        try {
            return QUI\ERP\Accounting\Contracts\Handler::getInstance()->get($contractId);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }
    }

    /**
     * Permanently links this MembershipUser to a contract (quiqqer/contracts)
     *
     * This causes the end date of this MembershipUser to be equal with the contract end date.
     *
     * @param int $contractId
     * @return void
     * @throws Exception
     */
    public function linkToContract(int $contractId): void
    {
        try {
            $Contract = ContractsHandler::getInstance()->getContract($contractId);
            $ContractCycleEndDate = $Contract->getCycleEndDate();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return;
        }

        $this->setAttribute('contractId', $contractId);

        if ($ContractCycleEndDate) {
            $this->setAttribute('endDate', $ContractCycleEndDate->format('Y-m-d 23:59:59'));
        }

        $this->update();
    }

    /**
     * Add an entry to the membership user history
     *
     * @param string $type - History entry type (see \QUI\Memberships\Users\Handler)
     * @param string $msg (optional) - additional custom message
     */
    public function addHistoryEntry(string $type, string $msg = ""): void
    {
        $history = $this->getHistory();
        $User = QUI::getUserBySession();

        if (empty($msg)) {
            $msg = "";
        }

        $history[] = [
            'type' => $type,
            'time' => Utils::getFormattedTimestamp(),
            'user' => $User->getName() . ' (' . $User->getId() . ')',
            'msg' => $msg
        ];

        $this->setAttribute('history', json_encode($history));
    }

    /**
     * Get history data of this MembershipUser
     *
     * @return array
     */
    public function getHistory(): array
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
     * @param DateTime|string $date - Formatted date YYYY-MM-DD HH:MM:SS or \DateTime object
     * @return string|false - formatted date or false on error
     * @throws Exception
     */
    protected function formatDate(DateTime | string $date): bool | string
    {
        if (empty($date) || $date === '0000-00-00 00:00:00') {
            return false;
        } elseif ($date instanceof DateTime) {
            $date = $date->format('Y-m-d H:i:s');
        }

        $Locale = $this->getUser()->getLocale();
        $lang = $Locale->getCurrent();
        $Conf = QUI::getPackage('quiqqer/memberships')->getConfig();

        switch (MembershipUsersHandler::getDurationMode()) {
            case MembershipUsersHandler::DURATION_MODE_DAY:
                $dateFormat = $Conf->get('date_formats_short', $lang);

                // fallback to default value
                if (empty($dateFormat)) {
                    if (QUI::getPackageManager()->isInstalled('quiqqer/erp')) {
                        $dateFormat = QUI\ERP\Defaults::getDateFormat($lang);
                    } else {
                        $dateFormat = '';
                    }
                }
                break;

            default:
                $dateFormat = $Conf->get('date_formats_long', $lang);

                // fallback to default value
                if (empty($dateFormat)) {
                    if (QUI::getPackageManager()->isInstalled('quiqqer/erp')) {
                        $dateFormat = QUI\ERP\Defaults::getTimestampFormat($lang);
                    } else {
                        $dateFormat = match ($lang) {
                            'de' => 'dd.MM.yyyy HH:mm:ss',
                            default => 'MMM dd, yyyy, HH:mm:ss',
                        };
                    }
                }
        }

        return $Locale->formatDate(strtotime($date), $dateFormat);
    }

    /**
     * Get membership data for frontend view/edit purposes with correctly formatted dates
     *
     * @return array
     * @throws Exception
     */
    public function getFrontendViewData(): array
    {
        $QuiqqerUser = $this->getUser();
        $Membership = $this->getMembership();
        $Locale = QUI::getLocale();

        // determine source of title, short and content
        $viewDataMode = MembershipUsersHandler::getSetting('viewDataMode');
        $productId = $this->getAttribute('productId');

        if (
            $viewDataMode === 'product'
            && !empty($productId)
            && Utils::isQuiqqerProductsInstalled()
        ) {
            $Product = ProductsHandler::getProduct($productId);
            $title = $Product->getTitle($Locale);
            $description = $Product->getDescription($Locale);
            $content = '';
        } else {
            $title = $Membership->getTitle($Locale);
            $description = $Membership->getDescription($Locale);
            $content = $Membership->getContent($Locale);
        }

        $CurrentCancelEndDate = $this->getCurrentCancelEndDate();
        $CancelUntilDate = false;
        $cancelAllowed = !$this->isCancelled();
        $Contract = $this->getContract();

        if (!$this->isCancelled() && $Contract) {
            try {
                if (!$Contract->isInPeriodOfNotice()) {
                    $cancelAllowed = false;
                }

                $PeriodOfNoticeInterval = $Contract->getPeriodOfNoticeInterval();
                $EndBaseDate = clone $CurrentCancelEndDate;
                $EndBaseDate->setTime(0, 0);
                $EndBaseDate->sub(date_interval_create_from_date_string('1 second'));

                $CancelUntilDate = clone $EndBaseDate;

                if ($PeriodOfNoticeInterval) {
                    $CancelUntilDate = $EndBaseDate->sub($PeriodOfNoticeInterval);
                }
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        $addedDate = $this->formatDate($this->getAttribute('addedDate'));
        $CycleEndDate = $this->getCycleEndDate();
        $cycleEndDate = $CycleEndDate ? $this->formatDate($CycleEndDate) : '-';
        $cycleBeginDate = $this->formatDate($this->getCycleBeginDate());
        $NextCycleEndDate = $this->getNextCycleEndDate();
        $nextCycleEndDate = $NextCycleEndDate ? $this->formatDate($NextCycleEndDate) : '-';

        // Determine cancel info text
        if ($Contract) {
            if ($Contract->getPeriodOfNoticeInterval()) {
                if ($Contract->isInPeriodOfNotice()) {
                    $cancelInfoText = QUI::getLocale()->get(
                        'quiqqer/memberships',
                        'MembershipUser.cancel.info_text.cancel_until_date',
                        [
                            'addedDate' => $addedDate,
                            'cancelUntilDate' => $this->formatDate($CancelUntilDate),
                            'cycleEndDate' => $cycleEndDate,
                            'nextCycleEndDate' => $nextCycleEndDate
                        ]
                    );
                } else {
                    $cancelInfoText = QUI::getLocale()->get(
                        'quiqqer/memberships',
                        'MembershipUser.cancel.info_text.period_of_notice_expired',
                        [
                            'addedDate' => $addedDate,
                            'cancelUntilDate' => $this->formatDate($CancelUntilDate),
                            'cycleBeginDate' => $cycleBeginDate,
                            'cycleEndDate' => $cycleEndDate,
                            'nextCycleEndDate' => $nextCycleEndDate
                        ]
                    );
                }
            } else {
                $cancelInfoText = QUI::getLocale()->get(
                    'quiqqer/memberships',
                    'MembershipUser.cancel.info_text.cycle_cancel_anytime',
                    [
                        'addedDate' => $addedDate,
                        'cycleEndDate' => $cycleEndDate,
                        'nextCycleEndDate' => $nextCycleEndDate
                    ]
                );
            }
        } elseif ($this->getMembership()->isInfinite()) {
            $cancelInfoText = QUI::getLocale()->get(
                'quiqqer/memberships',
                'MembershipUser.cancel.info_text.cancel_anytime'
            );
        } else {
            $cancelInfoText = QUI::getLocale()->get(
                'quiqqer/memberships',
                'MembershipUser.cancel.info_text.cycle_cancel_anytime',
                [
                    'addedDate' => $addedDate,
                    'cycleEndDate' => $cycleEndDate,
                    'nextCycleEndDate' => $nextCycleEndDate
                ]
            );
        }

        return [
            'id' => $this->getId(),
            'userId' => $QuiqqerUser->getId(),
            'membershipId' => $Membership->getId(),
            'membershipTitle' => $title,
            'membershipShort' => $description,
            'membershipContent' => $content,
            'username' => $QuiqqerUser->getUsername(),
            'fullName' => $QuiqqerUser->getName(),
            'addedDate' => $addedDate,
            'beginDate' => $cycleBeginDate,
            'endDate' => $cycleEndDate,
            'cancelEndDate' => $this->formatDate($CurrentCancelEndDate),
            'cancelDate' => $this->formatDate($this->getAttribute('cancelDate')),
//            'cancelUntilDate'   => $CancelUntilDate ? $this->formatDate($CancelUntilDate) : false,
            'cancelStatus' => $this->getAttribute('cancelStatus'),
            'cancelAllowed' => $cancelAllowed,
            'cancelInfoText' => $cancelInfoText,
//            'archived'        => $this->isArchived(),
//            'archiveReason'   => $this->getAttribute('archiveReason'),
            'cancelled' => $this->isCancelled(),
            'autoExtend' => $Membership->isAutoExtend(),
            'infinite' => $Membership->isInfinite()
        ];
    }

    /**
     * Get membership data for backend view/edit purposes
     *
     * @return array
     * @throws Exception
     */
    public function getBackendViewData(): array
    {
        $QuiqqerUser = $this->getUser();
        $Membership = $this->getMembership();

        return [
            'id' => $this->getId(),
            'userId' => $this->getUserId(),
            'membershipId' => $Membership->getId(),
            'membershipTitle' => $Membership->getTitle(),
            'username' => $QuiqqerUser ? $QuiqqerUser->getUsername() : '-',
            'firstname' => $QuiqqerUser ? $QuiqqerUser->getAttribute('firstname') : '-',
            'lastname' => $QuiqqerUser ? $QuiqqerUser->getAttribute('lastname') : '-',
            'fullName' => $QuiqqerUser ? $QuiqqerUser->getName() : '-',
            'addedDate' => $this->getAttribute('addedDate'),
            'beginDate' => $this->getAttribute('beginDate'),
            'endDate' => $this->getAttribute('endDate'),
            'archived' => $this->isArchived(),
            'archiveReason' => $this->getAttribute('archiveReason'),
            'archiveDate' => $this->getAttribute('archiveDate'),
            'cancelled' => $this->isCancelled(),
            'extraData' => $this->getExtraData(),
            'infinite' => $Membership->isInfinite(),
            'contractId' => $this->getContractId()
        ];
    }

    /**
     * Get Verification object for MembershipUser cancellation
     *
     * @return CancelVerification
     */
    protected function getCancelVerification(): CancelVerification
    {
        return new CancelVerification();
    }

    /**
     * Get Verification object for MembershipUser cancel abort
     *
     * @return AbortCancelVerification
     */
    protected function getAbortCancelVerification(): AbortCancelVerification
    {
        return new AbortCancelVerification();
    }

    /**
     * Email the membership user
     *
     * @param string $subject - mail subject
     * @param string $templateFile
     * @param array $templateVars (optional) - additional template variables (besides $this)
     * @return void
     *
     * @throws Exception
     */
    public function sendMail(string $subject, string $templateFile, array $templateVars = []): void
    {
        $User = $this->getUser();
        $email = $User->getAttribute('email');

        if (empty($email)) {
            QUI\System\Log::addError(
                'Could not send mail to user #' . $User->getId() . ' because the user has'
                . ' no email address!'
            );

            return;
        }

        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign(
            array_merge(
                [
                    'MembershipUser' => $this,
                    'Locale' => $this->getUser()->getLocale(),
                    'data' => $this->getFrontendViewData()
                ],
                $templateVars
            )
        );

        $template = $Engine->fetch($templateFile);

        $Mailer = new Mailer();

        $Mailer->addRecipient($email, $User->getName());
        $Mailer->setSubject($subject);
        $Mailer->setBody($template);

        try {
            $Mailer->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            QUI\System\Log::addError($e->getMessage());
        }
    }

    /**
     * Set any extra text data to the MembershipUser
     *
     * This is meant for extra information that is not already covered by the history.
     *
     * @param string $key
     * @param string $value
     */
    public function setExtraData(string $key, string $value): void
    {
        $extraData = $this->getExtraData();

        $User = QUI::getUserBySession();
        $userString = $User->getName() . ' (' . $User->getId() . ')';
        $editString = Utils::getFormattedTimestamp() . ' - ' . $userString;

        if (isset($extraData[$key])) {
            $extraData[$key]['edit'] = $editString;
            $extraData[$key]['value'] = $value;
        } else {
            $extraData[$key] = [
                'value' => $value,
                'add' => $editString,
                'edit' => '-'
            ];
        }

        $this->setAttribute('extraData', json_encode($extraData));
    }

    /**
     * Get extra data of this MembershipUser
     *
     * @param string|null $key (optional) - If omitted return all extra data
     * @return array|string|false
     */
    public function getExtraData(null | string $key = null): bool | array | string
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
     * Get begin Date of the current cycle
     *
     * @return DateTime
     */
    public function getCycleBeginDate(): DateTime
    {
        return date_create($this->getAttribute('beginDate'));
    }

    /**
     * Get end Date of the current cycle
     *
     * @return DateTime|false - DateTime of the cycle end or false if Membership has no cycle end (i.e. is infinite)
     * @throws Exception
     */
    public function getCycleEndDate(): DateTime | bool
    {
        $Contract = $this->getContract();

        if ($Contract) {
            return $Contract->getCycleEndDate();
        }

        if ($this->getMembership()->isInfinite()) {
            return false;
        }

        return date_create($this->getAttribute('endDate'));
    }

    /**
     * Get begin date of the (hypothetical) next cycle
     *
     * @return DateTime|false - DateTime of the cycle end or false if Membership has no next cycle (i.e. is infinite)
     * @throws Exception
     */
    public function getNextCycleBeginDate(): DateTime | bool
    {
        $Contract = $this->getContract();

        if ($Contract) {
            $EndDate = $Contract->getCycleEndDate();
            $NextBeginDate = clone $EndDate;
            $NextBeginDate->add(date_interval_create_from_date_string('1 day'));
            $NextBeginDate->setTime(0, 0);

            return $NextBeginDate;
        }

        if ($this->getMembership()->isInfinite()) {
            return false;
        }

        $EndDate = $this->getCycleEndDate();

        if (!$EndDate) {
            return false;
        }

        $NextBeginDate = clone $EndDate;

        switch (MembershipUsersHandler::getDurationMode()) {
            case MembershipUsersHandler::DURATION_MODE_EXACT:
                $NextBeginDate->add(date_interval_create_from_date_string('1 second'));
                break;

            default:
                $NextBeginDate->add(date_interval_create_from_date_string('1 day'));
                $NextBeginDate->setTime(0, 0);
        }

        return $NextBeginDate;
    }

    /**
     * Get the end Date of the (hypothetical) next cycle
     *
     * @return DateTime|false - DateTime of the next cycle end or false if Membership has no next cycle end (i.e. is infinite)
     * @throws Exception
     */
    public function getNextCycleEndDate(): DateTime | bool
    {
        $Contract = $this->getContract();

        if ($Contract) {
            return $Contract->getNextCycleEndDate();
        }

        $Membership = $this->getMembership();

        if ($Membership->isInfinite()) {
            return false;
        }

        $NextCycleBeginDate = $this->getNextCycleBeginDate();

        if (!$NextCycleBeginDate) {
            return false;
        }

        $start = $NextCycleBeginDate->format('Y-m-d');
        $duration = explode('-', $Membership->getAttribute('duration'));
        $durationCount = $duration[0];
        $durationScope = $duration[1];

        switch (MembershipUsersHandler::getDurationMode()) {
            case MembershipUsersHandler::DURATION_MODE_DAY:
                $endTime = strtotime($start . ' +' . $durationCount . ' ' . $durationScope);
                $beginOfDay = strtotime("midnight", $endTime);
                $end = strtotime("tomorrow", $beginOfDay) - 1;
                break;

            default:
                $end = strtotime($start . ' +' . $durationCount . ' ' . $durationScope);
        }

        return date_create('@' . $end);
    }

    /**
     * Calculates the date the membership for this user would end
     * if it was cancelled NOW
     *
     * @return DateTime|bool
     * @throws Exception
     */
    public function getCurrentCancelEndDate(): DateTime | bool
    {
        /**
         * If a contract is connected to this MembershipUser
         * the contract cancel termination date has priority!
         */
        $Contract = $this->getContract();

        if ($Contract) {
            try {
                return $Contract->getCurrentCancelTerminationDate();
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        return $this->getCycleEndDate();
    }
}
