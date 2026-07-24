<?php

namespace QUI\Memberships\Users;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Contracts\Contract;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Memberships\Membership;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Interface\VerificationFactoryInterface;
use QUI\Verification\Interface\VerificationRepositoryInterface;
use ReflectionProperty;

require_once __DIR__ . '/TestableMembershipUser.php';

class MembershipUserTest extends TestCase
{
    public function testIdentityFlagsAndContractId(): void
    {
        $MembershipUser = $this->membershipUserMock(['getAttribute']);
        $MembershipUser->method('getAttribute')->willReturnMap([
            ['userId', 'user-uuid'],
            ['cancelled', 1],
            ['archived', 0],
            ['contractId', '42']
        ]);

        self::assertSame('user-uuid', $MembershipUser->getUserId());
        self::assertTrue($MembershipUser->isCancelled());
        self::assertFalse($MembershipUser->isArchived());
        self::assertSame(42, $MembershipUser->getContractId());
    }

    public function testEmptyContractIdReturnsFalse(): void
    {
        $MembershipUser = $this->membershipUserMock(['getAttribute']);
        $MembershipUser->method('getAttribute')->willReturn(null);

        self::assertFalse($MembershipUser->getContractId());
        self::assertFalse($MembershipUser->getContract());
    }

    public function testUnknownContractCannotBeLoadedOrLinked(): void
    {
        $MembershipUser = $this->membershipUserMock([
            'getContractId',
            'setAttribute',
            'update'
        ]);
        $MembershipUser->method('getContractId')->willReturn(2147483647);
        $MembershipUser->expects(self::never())->method('setAttribute');
        $MembershipUser->expects(self::never())->method('update');

        self::assertFalse($MembershipUser->getContract());

        $MembershipUser->linkToContract(2147483647);
    }

    public function testGetUserSupportsUuidAndHandlesUnknownIdentifier(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $MembershipUser = $this->membershipUserMock(['getUserId']);
        $MembershipUser->method('getUserId')->willReturn((string)$SystemUser->getUUID());

        self::assertSame((string)$SystemUser->getUUID(), (string)$MembershipUser->getUser()?->getUUID());

        $MissingMembershipUser = $this->membershipUserMock(['getUserId']);
        $MissingMembershipUser->method('getUserId')->willReturn('missing-phpunit-user');

        self::assertNull($MissingMembershipUser->getUser());
    }

    public function testGetUserOrThrowReportsMissingMembershipAndUserIds(): void
    {
        $MembershipUser = $this->membershipUserMock(['getUser', 'getUserId', 'getId']);
        $MembershipUser->method('getUser')->willReturn(null);
        $MembershipUser->method('getUserId')->willReturn('missing-user');
        $MembershipUser->method('getId')->willReturn(17);

        $this->expectException(QUI\Memberships\Exception::class);
        $this->expectExceptionMessage(
            'QUIQQER user #missing-user for membership user #17 not found.'
        );

        $MembershipUser->getUserOrThrow();
    }

    public function testHistoryReadAndAppend(): void
    {
        $MembershipUser = $this->membershipUserMock(['getAttribute', 'setAttribute']);
        $MembershipUser->method('getAttribute')
            ->with('history')
            ->willReturn('[{"type":"created","msg":""}]');
        $MembershipUser->expects(self::once())
            ->method('setAttribute')
            ->with(
                'history',
                self::callback(static function (string $json): bool {
                    $history = json_decode($json, true);

                    return count($history) === 2
                        && $history[1]['type'] === Handler::HISTORY_TYPE_MISC
                        && $history[1]['msg'] === 'note'
                        && $history[1]['user'] !== ''
                        && $history[1]['time'] !== '';
                })
            );

        self::assertSame(
            [['type' => 'created', 'msg' => '']],
            $MembershipUser->getHistory()
        );

        $MembershipUser->addHistoryEntry(Handler::HISTORY_TYPE_MISC, 'note');
    }

    public function testEmptyHistoryReturnsArray(): void
    {
        $MembershipUser = $this->membershipUserMock(['getAttribute']);
        $MembershipUser->method('getAttribute')->willReturn(null);

        self::assertSame([], $MembershipUser->getHistory());
    }

    public function testExtraDataReadCreateAndUpdate(): void
    {
        $existing = [
            'reference' => [
                'value' => 'old',
                'add' => 'created',
                'edit' => '-'
            ]
        ];
        $Reader = $this->membershipUserMock(['getAttribute']);
        $Reader->method('getAttribute')
            ->with('extraData')
            ->willReturn(json_encode($existing));

        self::assertSame($existing, $Reader->getExtraData());
        self::assertSame('old', $Reader->getExtraData('reference'));
        self::assertFalse($Reader->getExtraData('missing'));

        $Updater = $this->membershipUserMock(['getExtraData', 'setAttribute']);
        $Updater->method('getExtraData')->willReturn($existing);
        $Updater->expects(self::once())
            ->method('setAttribute')
            ->with(
                'extraData',
                self::callback(static function (string $json): bool {
                    $data = json_decode($json, true);

                    return $data['reference']['value'] === 'new'
                        && $data['reference']['add'] === 'created'
                        && $data['reference']['edit'] !== '-';
                })
            );

        $Updater->setExtraData('reference', 'new');
    }

    public function testSetExtraDataCreatesMetadata(): void
    {
        $MembershipUser = $this->membershipUserMock(['getExtraData', 'setAttribute']);
        $MembershipUser->method('getExtraData')->willReturn([]);
        $MembershipUser->expects(self::once())
            ->method('setAttribute')
            ->with(
                'extraData',
                self::callback(static function (string $json): bool {
                    $data = json_decode($json, true);

                    return $data['reference']['value'] === 'new'
                        && $data['reference']['add'] !== ''
                        && $data['reference']['edit'] === '-';
                })
            );

        $MembershipUser->setExtraData('reference', 'new');
    }

    public function testCycleDatesWithoutContract(): void
    {
        $Membership = $this->createMock(Membership::class);
        $Membership->method('isInfinite')->willReturn(false);
        $Membership->method('getAttribute')
            ->with('duration')
            ->willReturn('1-month');
        $MembershipUser = $this->membershipUserMock([
            'getAttribute',
            'getContract',
            'getMembership'
        ]);
        $MembershipUser->method('getAttribute')->willReturnMap([
            ['beginDate', '2024-01-01 08:30:00'],
            ['endDate', '2024-01-31 23:59:59']
        ]);
        $MembershipUser->method('getContract')->willReturn(false);
        $MembershipUser->method('getMembership')->willReturn($Membership);

        self::assertSame(
            '2024-01-01 08:30:00',
            $MembershipUser->getCycleBeginDate()->format('Y-m-d H:i:s')
        );
        self::assertSame(
            '2024-01-31 23:59:59',
            $MembershipUser->getCycleEndDate()->format('Y-m-d H:i:s')
        );

        $NextBeginDate = $MembershipUser->getNextCycleBeginDate();
        $NextEndDate = $MembershipUser->getNextCycleEndDate();

        self::assertInstanceOf(DateTime::class, $NextBeginDate);
        self::assertInstanceOf(DateTime::class, $NextEndDate);

        if (Handler::getDurationMode() === Handler::DURATION_MODE_EXACT) {
            self::assertSame('2024-02-01 00:00:00', $NextBeginDate->format('Y-m-d H:i:s'));
        } else {
            self::assertSame('2024-02-01 00:00:00', $NextBeginDate->format('Y-m-d H:i:s'));
            self::assertSame('23:59:59', $NextEndDate->format('H:i:s'));
        }

        self::assertSame(
            $MembershipUser->getCycleEndDate()->format('c'),
            $MembershipUser->getCurrentCancelEndDate()->format('c')
        );
    }

    public function testCycleDatesReturnFalseForInfiniteMembership(): void
    {
        $Membership = $this->createMock(Membership::class);
        $Membership->method('isInfinite')->willReturn(true);
        $MembershipUser = $this->membershipUserMock(['getContract', 'getMembership']);
        $MembershipUser->method('getContract')->willReturn(false);
        $MembershipUser->method('getMembership')->willReturn($Membership);

        self::assertFalse($MembershipUser->getCycleEndDate());
        self::assertFalse($MembershipUser->getNextCycleBeginDate());
        self::assertFalse($MembershipUser->getNextCycleEndDate());
    }

    public function testContractCycleDatesHavePriority(): void
    {
        $Contract = $this->getMockBuilder(Contract::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getCycleEndDate',
                'getNextCycleEndDate',
                'getCurrentCancelTerminationDate'
            ])
            ->getMock();
        $Contract->method('getCycleEndDate')
            ->willReturn(new DateTime('2024-02-29 23:59:59'));
        $Contract->method('getNextCycleEndDate')
            ->willReturn(new DateTime('2024-03-31 23:59:59'));
        $Contract->method('getCurrentCancelTerminationDate')
            ->willReturn(new DateTime('2024-04-30 23:59:59'));
        $MembershipUser = $this->membershipUserMock(['getContract']);
        $MembershipUser->method('getContract')->willReturn($Contract);

        self::assertSame(
            '2024-02-29 23:59:59',
            $MembershipUser->getCycleEndDate()->format('Y-m-d H:i:s')
        );
        self::assertSame(
            '2024-03-01 00:00:00',
            $MembershipUser->getNextCycleBeginDate()->format('Y-m-d H:i:s')
        );
        self::assertSame(
            '2024-03-31 23:59:59',
            $MembershipUser->getNextCycleEndDate()->format('Y-m-d H:i:s')
        );
        self::assertSame(
            '2024-04-30 23:59:59',
            $MembershipUser->getCurrentCancelEndDate()->format('Y-m-d H:i:s')
        );
    }

    public function testCycleEndDateRejectsMissingAndInvalidValues(): void
    {
        $Membership = $this->createMock(Membership::class);
        $Membership->method('isInfinite')->willReturn(false);
        $MembershipUser = $this->membershipUserMock([
            'getContract',
            'getMembership',
            'getAttribute'
        ]);
        $MembershipUser->method('getContract')->willReturn(false);
        $MembershipUser->method('getMembership')->willReturn($Membership);
        $MembershipUser->method('getAttribute')->willReturn(null);

        self::assertFalse($MembershipUser->getCycleEndDate());

        $InvalidMembershipUser = $this->membershipUserMock([
            'getContract',
            'getMembership',
            'getAttribute'
        ]);
        $InvalidMembershipUser->method('getContract')->willReturn(false);
        $InvalidMembershipUser->method('getMembership')->willReturn($Membership);
        $InvalidMembershipUser->method('getAttribute')->willReturn('invalid-date');

        self::assertFalse($InvalidMembershipUser->getCycleEndDate());
    }

    public function testCalcEndDateDelegatesToMembership(): void
    {
        $Start = new DateTime('2024-01-01 00:00:00');
        $Membership = $this->createMock(Membership::class);
        $Membership->expects(self::once())
            ->method('calcEndDate')
            ->with($Start->getTimestamp())
            ->willReturn('2024-02-01 00:00:00');
        $MembershipUser = $this->membershipUserMock(['getContractId', 'getMembership']);
        $MembershipUser->method('getContractId')->willReturn(false);
        $MembershipUser->method('getMembership')->willReturn($Membership);

        self::assertSame(
            '2024-02-01 00:00:00',
            $MembershipUser->calcEndDate($Start)->format('Y-m-d H:i:s')
        );
    }

    public function testCalcEndDateRejectsInfiniteMembership(): void
    {
        $Membership = $this->createMock(Membership::class);
        $Membership->method('calcEndDate')->willReturn(null);
        $MembershipUser = $this->membershipUserMock(['getContractId', 'getMembership']);
        $MembershipUser->method('getContractId')->willReturn(false);
        $MembershipUser->method('getMembership')->willReturn($Membership);

        $this->expectException(QUI\Memberships\Exception::class);
        $MembershipUser->calcEndDate(new DateTime('2024-01-01 00:00:00'));
    }

    public function testCalcEndDateFallsBackWhenLinkedContractIsMissing(): void
    {
        $Membership = $this->createMock(Membership::class);
        $Membership->method('calcEndDate')->willReturn('2024-02-01 00:00:00');
        $MembershipUser = $this->membershipUserMock(['getContractId', 'getMembership']);
        $MembershipUser->method('getContractId')->willReturn(2147483647);
        $MembershipUser->method('getMembership')->willReturn($Membership);

        self::assertSame(
            '2024-02-01 00:00:00',
            $MembershipUser->calcEndDate(
                new DateTime('2024-01-01 00:00:00')
            )->format('Y-m-d H:i:s')
        );
    }

    public function testExtendUpdatesCycleAndHistory(): void
    {
        $Begin = new DateTime('2024-02-01 00:00:00');
        $End = new DateTime('2024-02-29 23:59:59');
        $MembershipUser = $this->membershipUserMock([
            'getAttribute',
            'setAttributes',
            'addHistoryEntry',
            'update',
            'sendAutoExtendMail'
        ]);
        $MembershipUser->method('getAttribute')
            ->with('extendCounter')
            ->willReturn(2);
        $MembershipUser->expects(self::once())
            ->method('setAttributes')
            ->with([
                'beginDate' => '2024-02-01 00:00:00',
                'endDate' => '2024-02-29 23:59:59',
                'extendCounter' => 3
            ]);
        $MembershipUser->expects(self::once())->method('addHistoryEntry');
        $MembershipUser->expects(self::once())->method('update');
        $MembershipUser->expects(self::once())->method('sendAutoExtendMail');

        $MembershipUser->extend(true, $Begin, $End);
    }

    public function testManualExtendOnlyUpdatesEndDate(): void
    {
        $Begin = new DateTime('2024-02-01 00:00:00');
        $End = new DateTime('2024-02-29 23:59:59');
        $MembershipUser = $this->membershipUserMock([
            'setAttributes',
            'addHistoryEntry',
            'update',
            'sendManualExtendMail'
        ]);
        $MembershipUser->expects(self::once())
            ->method('setAttributes')
            ->with(['endDate' => '2024-02-29 23:59:59']);
        $MembershipUser->expects(self::once())->method('addHistoryEntry');
        $MembershipUser->expects(self::once())->method('update');
        $MembershipUser->expects(self::once())->method('sendManualExtendMail');

        $MembershipUser->extend(false, $Begin, $End);
    }

    public function testExtendCalculatesMissingCycleDates(): void
    {
        $Config = QUI\Memberships\Handler::getConfig();
        $previousExtendMode = $Config->get('membershipusers', 'extendMode');
        $Config->set('membershipusers', 'extendMode', Handler::EXTEND_MODE_RESET);
        $Begin = new DateTime('2024-02-01 00:00:00');
        $End = new DateTime('2024-02-29 23:59:59');
        $MembershipUser = $this->membershipUserMock([
            'getNextCycleBeginDate',
            'getNextCycleEndDate',
            'getAttribute',
            'setAttributes',
            'addHistoryEntry',
            'update',
            'sendAutoExtendMail'
        ]);
        $MembershipUser->expects(self::once())
            ->method('getNextCycleBeginDate')
            ->willReturn($Begin);
        $MembershipUser->expects(self::once())
            ->method('getNextCycleEndDate')
            ->willReturn($End);
        $MembershipUser->method('getAttribute')
            ->with('extendCounter')
            ->willReturn(0);
        $MembershipUser->expects(self::once())
            ->method('setAttributes')
            ->with([
                'beginDate' => '2024-02-01 00:00:00',
                'endDate' => '2024-02-29 23:59:59',
                'extendCounter' => 1
            ]);

        try {
            $MembershipUser->extend();
        } finally {
            $Config->set('membershipusers', 'extendMode', $previousExtendMode);
        }
    }

    public function testAutoCancelUpdatesFiniteMembership(): void
    {
        $Membership = $this->createMock(Membership::class);
        $Membership->method('isInfinite')->willReturn(false);
        $Membership->method('isDefault')->willReturn(false);
        $EndDate = new DateTime('2024-03-01 12:00:00');
        $MembershipUser = $this->membershipUserMock([
            'isCancelled',
            'getMembership',
            'setAttributes',
            'addHistoryEntry',
            'setEditUser',
            'setAttribute',
            'update'
        ]);
        $MembershipUser->method('isCancelled')->willReturn(false);
        $MembershipUser->method('getMembership')->willReturn($Membership);
        $MembershipUser->expects(self::once())
            ->method('setAttributes')
            ->with(self::callback(static fn(array $data): bool =>
                $data['cancelStatus'] === Handler::CANCEL_STATUS_CANCELLED_BY_SYSTEM
                && $data['cancelled'] === true
                && $data['cancelDate'] !== ''));
        $MembershipUser->expects(self::once())
            ->method('setAttribute')
            ->with('endDate', '2024-03-01 12:00:00');
        $MembershipUser->expects(self::once())->method('update');

        $MembershipUser->autoCancel($EndDate);
    }

    public function testAutoCancelStopsForCancelledInfiniteAndDefaultMemberships(): void
    {
        $Cancelled = $this->membershipUserMock(['isCancelled']);
        $Cancelled->method('isCancelled')->willReturn(true);
        $Cancelled->autoCancel();

        $InfiniteMembership = $this->createMock(Membership::class);
        $InfiniteMembership->method('isInfinite')->willReturn(true);
        $Infinite = $this->membershipUserMock(['isCancelled', 'getMembership']);
        $Infinite->method('isCancelled')->willReturn(false);
        $Infinite->method('getMembership')->willReturn($InfiniteMembership);
        $Infinite->autoCancel();

        $DefaultMembership = $this->createMock(Membership::class);
        $DefaultMembership->method('isInfinite')->willReturn(false);
        $DefaultMembership->method('isDefault')->willReturn(true);
        $Default = $this->membershipUserMock(['isCancelled', 'getMembership']);
        $Default->method('isCancelled')->willReturn(false);
        $Default->method('getMembership')->willReturn($DefaultMembership);
        $Default->autoCancel();

        self::assertTrue(true);
    }

    public function testAutoCancelHandlesPersistenceFailure(): void
    {
        $Membership = $this->createMock(Membership::class);
        $Membership->method('isInfinite')->willReturn(false);
        $Membership->method('isDefault')->willReturn(false);
        $MembershipUser = $this->membershipUserMock([
            'isCancelled',
            'getMembership',
            'setAttributes',
            'addHistoryEntry',
            'setEditUser',
            'update'
        ]);
        $MembershipUser->method('isCancelled')->willReturn(false);
        $MembershipUser->method('getMembership')->willReturn($Membership);
        $MembershipUser->expects(self::once())
            ->method('update')
            ->willThrowException(new \RuntimeException('Persistence failed'));

        $MembershipUser->autoCancel();
    }

    public function testConfirmManualCancelUpdatesState(): void
    {
        $EndDate = new DateTime('2024-03-01 12:00:00');
        $MembershipUser = $this->membershipUserMock([
            'isCancelled',
            'getCurrentCancelEndDate',
            'setAttributes',
            'addHistoryEntry',
            'update',
            'sendConfirmCancelMail'
        ]);
        $MembershipUser->method('isCancelled')->willReturn(false);
        $MembershipUser->method('getCurrentCancelEndDate')->willReturn($EndDate);
        $MembershipUser->expects(self::once())
            ->method('setAttributes')
            ->with([
                'cancelled' => true,
                'cancelStatus' => Handler::CANCEL_STATUS_CANCELLED,
                'cancelEndDate' => '2024-03-01 12:00:00'
            ]);
        $MembershipUser->expects(self::once())->method('update');
        $MembershipUser->expects(self::once())->method('sendConfirmCancelMail');

        $MembershipUser->confirmManualCancel();
    }

    public function testConfirmManualCancelStopsForExistingCancellation(): void
    {
        $MembershipUser = $this->membershipUserMock([
            'isCancelled',
            'getCurrentCancelEndDate',
            'setAttributes'
        ]);
        $MembershipUser->method('isCancelled')->willReturn(true);
        $MembershipUser->expects(self::never())->method('getCurrentCancelEndDate');
        $MembershipUser->expects(self::never())->method('setAttributes');

        $MembershipUser->confirmManualCancel();
    }

    public function testConfirmManualCancelRejectsMissingEndDate(): void
    {
        $MembershipUser = $this->membershipUserMock([
            'isCancelled',
            'getCurrentCancelEndDate'
        ]);
        $MembershipUser->method('isCancelled')->willReturn(false);
        $MembershipUser->method('getCurrentCancelEndDate')->willReturn(false);

        $this->expectException(QUI\Memberships\Exception::class);
        $MembershipUser->confirmManualCancel();
    }

    public function testExpireAndCancelArchiveAndNotifyUser(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getLocale')->willReturn(QUI::getLocale());

        foreach (
            [
                'expire' => Handler::ARCHIVE_REASON_EXPIRED,
                'cancel' => Handler::ARCHIVE_REASON_CANCELLED
            ] as $method => $reason
        ) {
            $MembershipUser = $this->membershipUserMock([
                'addHistoryEntry',
                'archive',
                'getUserOrThrow',
                'sendMail'
            ]);
            $MembershipUser->method('getUserOrThrow')->willReturn($User);
            $MembershipUser->expects(self::once())
                ->method('archive')
                ->with($reason);
            $MembershipUser->expects(self::once())->method('sendMail');

            $MembershipUser->{$method}();
        }
    }

    public function testStartManualCancelStopsForExistingCancellation(): void
    {
        $User = $this->sessionUserDouble();
        $MembershipUser = $this->membershipUserMock(['getUserOrThrow', 'isCancelled']);
        $MembershipUser->method('getUserOrThrow')->willReturn($User);
        $MembershipUser->method('isCancelled')->willReturn(true);

        $MembershipUser->startManualCancel();
        self::assertTrue(true);
    }

    public function testStartManualCancelStopsForInfiniteAndDefaultMemberships(): void
    {
        $User = $this->sessionUserDouble();

        foreach ([[true, false], [false, true]] as [$infinite, $default]) {
            $Membership = $this->createMock(Membership::class);
            $Membership->method('isInfinite')->willReturn($infinite);
            $Membership->method('isDefault')->willReturn($default);
            $MembershipUser = $this->membershipUserMock([
                'getUserOrThrow',
                'isCancelled',
                'getMembership'
            ]);
            $MembershipUser->method('getUserOrThrow')->willReturn($User);
            $MembershipUser->method('isCancelled')->willReturn(false);
            $MembershipUser->method('getMembership')->willReturn($Membership);

            $MembershipUser->startManualCancel();
        }

        self::assertTrue(true);
    }

    public function testStartManualCancelRejectsMissingEmail(): void
    {
        $User = $this->sessionUserDouble();
        $User->method('getAttribute')->with('email')->willReturn('');
        $Membership = $this->createMock(Membership::class);
        $Membership->method('isInfinite')->willReturn(false);
        $Membership->method('isDefault')->willReturn(false);
        $MembershipUser = $this->membershipUserMock([
            'getUserOrThrow',
            'isCancelled',
            'getMembership'
        ]);
        $MembershipUser->method('getUserOrThrow')->willReturn($User);
        $MembershipUser->method('isCancelled')->willReturn(false);
        $MembershipUser->method('getMembership')->willReturn($Membership);

        $this->expectException(QUI\Memberships\Exception::class);
        $MembershipUser->startManualCancel();
    }

    public function testStartManualCancelRejectsDifferentSessionUser(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getUUID')->willReturn('different-user-uuid');
        $MembershipUser = $this->membershipUserMock(['getUserOrThrow']);
        $MembershipUser->method('getUserOrThrow')->willReturn($User);

        $this->expectException(QUI\Memberships\Exception::class);
        $MembershipUser->startManualCancel();
    }

    public function testStartManualCancelCreatesVerificationAndPersistsPendingState(): void
    {
        $User = $this->sessionUserDouble();
        $User->method('getAttribute')->with('email')->willReturn('user@example.invalid');
        $User->method('getLocale')->willReturn(QUI::getLocale());
        $Membership = $this->createMock(Membership::class);
        $Membership->method('isInfinite')->willReturn(false);
        $Membership->method('isDefault')->willReturn(false);
        $Verification = $this->createLinkVerification(
            'cancel',
            'https://example.invalid/cancel'
        );
        $Factory = $this->createMock(VerificationFactoryInterface::class);
        $Factory->expects(self::once())
            ->method('createLinkVerification')
            ->willReturn($Verification);
        $MembershipUser = $this->membershipUserMock([
            'getUserOrThrow',
            'isCancelled',
            'getMembership',
            'getCurrentCancelEndDate',
            'setAttributes',
            'addHistoryEntry',
            'setEditUser',
            'update',
            'sendMail'
        ]);
        $MembershipUser->method('getUserOrThrow')->willReturn($User);
        $MembershipUser->method('isCancelled')->willReturn(false);
        $MembershipUser->method('getMembership')->willReturn($Membership);
        $MembershipUser->method('getCurrentCancelEndDate')
            ->willReturn(new DateTime('2024-03-31 23:59:59'));
        $MembershipUser->expects(self::once())
            ->method('setAttributes')
            ->with(self::callback(static fn(array $data): bool =>
                $data['cancelStatus'] === Handler::CANCEL_STATUS_CANCEL_CONFIRM_PENDING
                && $data['cancelDate'] !== ''
                && $data['cancelEndDate'] === '2024-03-31 23:59:59'));
        $MembershipUser->expects(self::once())
            ->method('addHistoryEntry')
            ->with(Handler::HISTORY_TYPE_CANCEL_START);
        $MembershipUser->expects(self::once())->method('update');
        $MembershipUser->expects(self::once())
            ->method('sendMail')
            ->with(
                self::isType('string'),
                self::stringEndsWith('mail_startcancel.html'),
                self::callback(static fn(array $data): bool =>
                    $data['cancelUrl'] === 'https://example.invalid/cancel'
                    && $data['cancelDate'] !== ''
                    && $data['cancelEndDate'] !== '')
            );
        $this->setPrivateProperty($MembershipUser, 'id', 9, QUI\CRUD\Child::class);
        $this->setPrivateProperty($MembershipUser, 'verificationFactory', $Factory);

        $MembershipUser->startManualCancel();
    }

    public function testStartAbortCancelStopsForUnrelatedStatus(): void
    {
        $User = $this->sessionUserDouble();
        $MembershipUser = $this->membershipUserMock(['getUserOrThrow', 'getAttribute']);
        $MembershipUser->method('getUserOrThrow')->willReturn($User);
        $MembershipUser->method('getAttribute')
            ->with('cancelStatus')
            ->willReturn(Handler::CANCEL_STATUS_NOT_CANCELLED);

        $MembershipUser->startAbortCancel();
        self::assertTrue(true);
    }

    public function testStartAbortCancelRejectsSystemCancellation(): void
    {
        $User = $this->sessionUserDouble();
        $MembershipUser = $this->membershipUserMock(['getUserOrThrow', 'getAttribute']);
        $MembershipUser->method('getUserOrThrow')->willReturn($User);
        $MembershipUser->method('getAttribute')
            ->with('cancelStatus')
            ->willReturn(Handler::CANCEL_STATUS_CANCELLED_BY_SYSTEM);

        $this->expectException(QUI\Memberships\Exception::class);
        $MembershipUser->startAbortCancel();
    }

    public function testStartAbortCancelRejectsDifferentSessionUser(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getUUID')->willReturn('different-user-uuid');
        $MembershipUser = $this->membershipUserMock(['getUserOrThrow']);
        $MembershipUser->method('getUserOrThrow')->willReturn($User);

        $this->expectException(QUI\Memberships\Exception::class);
        $MembershipUser->startAbortCancel();
    }

    public function testStartAbortCancelRejectsMissingEmail(): void
    {
        $User = $this->sessionUserDouble();
        $User->method('getAttribute')->with('email')->willReturn('');
        $MembershipUser = $this->membershipUserMock(['getUserOrThrow', 'getAttribute']);
        $MembershipUser->method('getUserOrThrow')->willReturn($User);
        $MembershipUser->method('getAttribute')
            ->with('cancelStatus')
            ->willReturn(Handler::CANCEL_STATUS_CANCELLED);

        $this->expectException(QUI\Memberships\Exception::class);
        $MembershipUser->startAbortCancel();
    }

    public function testStartAbortCancelCreatesVerificationAndPersistsPendingState(): void
    {
        $User = $this->sessionUserDouble();
        $User->method('getAttribute')->with('email')->willReturn('user@example.invalid');
        $Verification = $this->createLinkVerification(
            'abort',
            'https://example.invalid/abort'
        );
        $Factory = $this->createMock(VerificationFactoryInterface::class);
        $Factory->expects(self::once())
            ->method('createLinkVerification')
            ->willReturn($Verification);
        $MembershipUser = $this->membershipUserMock([
            'getUserOrThrow',
            'getAttribute',
            'setAttributes',
            'addHistoryEntry',
            'setEditUser',
            'update',
            'sendMail'
        ]);
        $MembershipUser->method('getUserOrThrow')->willReturn($User);
        $MembershipUser->method('getAttribute')
            ->with('cancelStatus')
            ->willReturn(Handler::CANCEL_STATUS_CANCELLED);
        $MembershipUser->expects(self::once())
            ->method('setAttributes')
            ->with([
                'cancelStatus' => Handler::CANCEL_STATUS_ABORT_CANCEL_CONFIRM_PENDING
            ]);
        $MembershipUser->expects(self::once())
            ->method('addHistoryEntry')
            ->with(Handler::HISTORY_TYPE_CANCEL_ABORT_START);
        $MembershipUser->expects(self::once())->method('update');
        $MembershipUser->expects(self::once())
            ->method('sendMail')
            ->with(
                self::isType('string'),
                self::stringEndsWith('mail_startabortcancel.html'),
                ['abortCancelUrl' => 'https://example.invalid/abort']
            );
        $this->setPrivateProperty($MembershipUser, 'id', 9, QUI\CRUD\Child::class);
        $this->setPrivateProperty($MembershipUser, 'verificationFactory', $Factory);

        $MembershipUser->startAbortCancel();
    }

    public function testConfirmAbortCancelResetsStateWithoutVerification(): void
    {
        $Repository = $this->createMock(VerificationRepositoryInterface::class);
        $Repository->method('findByIdentifier')->willReturn(null);
        $MembershipUser = $this->membershipUserMock([
            'setAttributes',
            'addHistoryEntry',
            'setEditUser',
            'update'
        ]);
        $this->setPrivateProperty($MembershipUser, 'id', 9, QUI\CRUD\Child::class);
        $this->setPrivateProperty($MembershipUser, 'verificationRepository', $Repository);
        $MembershipUser->expects(self::once())
            ->method('setAttributes')
            ->with([
                'cancelDate' => null,
                'cancelStatus' => Handler::CANCEL_STATUS_NOT_CANCELLED,
                'cancelled' => false,
                'cancelEndDate' => null
            ]);
        $MembershipUser->expects(self::once())->method('update');

        $MembershipUser->confirmAbortCancel();
    }

    public function testConfirmAbortCancelDeletesExistingVerification(): void
    {
        $Verification = $this->createLinkVerification(
            'abort',
            'https://example.invalid/abort'
        );
        $Repository = $this->createMock(VerificationRepositoryInterface::class);
        $Repository->method('findByIdentifier')->willReturn($Verification);
        $Repository->expects(self::once())
            ->method('delete')
            ->with($Verification);
        $MembershipUser = $this->membershipUserMock([
            'setAttributes',
            'addHistoryEntry',
            'setEditUser',
            'update'
        ]);
        $this->setPrivateProperty($MembershipUser, 'id', 9, QUI\CRUD\Child::class);
        $this->setPrivateProperty($MembershipUser, 'verificationRepository', $Repository);
        $MembershipUser->expects(self::once())->method('update');

        $MembershipUser->confirmAbortCancel();
    }

    public function testConfirmationMailsUseUserLocale(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getLocale')->willReturn(QUI::getLocale());

        foreach (['sendManualExtendMail', 'sendConfirmCancelMail'] as $method) {
            $MembershipUser = $this->membershipUserMock(['getUserOrThrow', 'sendMail']);
            $MembershipUser->method('getUserOrThrow')->willReturn($User);
            $MembershipUser->expects(self::atMost(1))->method('sendMail');
            $MembershipUser->{$method}();
        }

        self::assertTrue(true);
    }

    public function testAutoExtendMailStopsForMissingUser(): void
    {
        $Config = QUI\Memberships\Handler::getConfig();
        $previousSetting = $Config->get('membershipusers', 'sendAutoExtendMail');
        $Config->set('membershipusers', 'sendAutoExtendMail', 1);
        $MembershipUser = $this->membershipUserMock(['getUser', 'sendMail']);
        $MembershipUser->method('getUser')->willReturn(null);
        $MembershipUser->expects(self::never())->method('sendMail');

        try {
            $MembershipUser->sendAutoExtendMailPublic();
        } finally {
            $Config->set(
                'membershipusers',
                'sendAutoExtendMail',
                $previousSetting
            );
        }
    }

    public function testConfirmCancelMailHandlesMissingUser(): void
    {
        $MembershipUser = $this->membershipUserMock([
            'getUserOrThrow',
            'sendMail'
        ]);
        $MembershipUser->method('getUserOrThrow')
            ->willThrowException(new \RuntimeException('User is missing'));
        $MembershipUser->expects(self::never())->method('sendMail');

        $MembershipUser->sendConfirmCancelMail();
    }

    public function testCancelReminderReturnsFalseWithoutVerification(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getLocale')->willReturn(QUI::getLocale());
        $Repository = $this->createMock(VerificationRepositoryInterface::class);
        $Repository->method('findByIdentifier')->willReturn(null);
        $MembershipUser = $this->membershipUserMock(['getUserOrThrow']);
        $MembershipUser->method('getUserOrThrow')->willReturn($User);
        $this->setPrivateProperty($MembershipUser, 'id', 9, QUI\CRUD\Child::class);
        $this->setPrivateProperty($MembershipUser, 'verificationRepository', $Repository);

        self::assertFalse($MembershipUser->sendConfirmCancelReminderMail());
    }

    public function testCancelReminderSendsExistingVerificationLinkAndPersistsHistory(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getLocale')->willReturn(QUI::getLocale());
        $Verification = $this->createLinkVerification(
            'cancel',
            'https://example.invalid/cancel'
        );
        $Repository = $this->createMock(VerificationRepositoryInterface::class);
        $Repository->method('findByIdentifier')->willReturn($Verification);
        $MembershipUser = $this->membershipUserMock([
            'getUserOrThrow',
            'sendMail',
            'addHistoryEntry',
            'update'
        ]);
        $MembershipUser->method('getUserOrThrow')->willReturn($User);
        $MembershipUser->expects(self::once())
            ->method('sendMail')
            ->with(
                self::isType('string'),
                self::stringEndsWith('mail_confirmcancel_reminder.html'),
                ['cancelUrl' => 'https://example.invalid/cancel']
            );
        $MembershipUser->expects(self::once())
            ->method('addHistoryEntry')
            ->with(Handler::HISTORY_TYPE_MISC, self::isType('string'));
        $MembershipUser->expects(self::once())->method('update');
        $this->setPrivateProperty($MembershipUser, 'id', 9, QUI\CRUD\Child::class);
        $this->setPrivateProperty($MembershipUser, 'verificationRepository', $Repository);

        self::assertTrue($MembershipUser->sendConfirmCancelReminderMail());
    }

    public function testFormatDateHandlesEmptyInvalidAndValidDates(): void
    {
        $MembershipUser = $this->membershipUserMock(['getUserOrThrow']);
        $MembershipUser->method('getUserOrThrow')->willReturn(QUI::getUsers()->getSystemUser());

        self::assertFalse($MembershipUser->formatDatePublic(null));
        self::assertFalse($MembershipUser->formatDatePublic('0000-00-00 00:00:00'));
        self::assertFalse($MembershipUser->formatDatePublic('invalid-date'));
        self::assertNotSame(
            '',
            $MembershipUser->formatDatePublic(new DateTime('2024-01-02 03:04:05'))
        );
    }

    public function testFrontendViewDataWithoutContract(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getUUID')->willReturn('user-uuid');
        $User->method('getUsername')->willReturn('username');
        $User->method('getName')->willReturn('Full Name');
        $Membership = $this->createMock(Membership::class);
        $Membership->method('getId')->willReturn(4);
        $Membership->method('getTitle')->willReturn('Membership');
        $Membership->method('getDescription')->willReturn('Description');
        $Membership->method('getContent')->willReturn('Content');
        $Membership->method('isAutoExtend')->willReturn(false);
        $Membership->method('isInfinite')->willReturn(false);
        $MembershipUser = $this->membershipUserMock([
            'getId',
            'getUserOrThrow',
            'getMembership',
            'getAttribute',
            'getCurrentCancelEndDate',
            'isCancelled',
            'getContract',
            'formatDate',
            'getCycleEndDate',
            'getCycleBeginDate',
            'getNextCycleEndDate'
        ]);
        $MembershipUser->method('getId')->willReturn(8);
        $MembershipUser->method('getUserOrThrow')->willReturn($User);
        $MembershipUser->method('getMembership')->willReturn($Membership);
        $MembershipUser->method('getAttribute')->willReturnMap([
            ['productId', null],
            ['addedDate', '2024-01-01 00:00:00'],
            ['cancelDate', null],
            ['cancelStatus', Handler::CANCEL_STATUS_NOT_CANCELLED]
        ]);
        $MembershipUser->method('getCurrentCancelEndDate')
            ->willReturn(new DateTime('2024-02-01 23:59:59'));
        $MembershipUser->method('isCancelled')->willReturn(false);
        $MembershipUser->method('getContract')->willReturn(false);
        $MembershipUser->method('formatDate')
            ->willReturnCallback(static fn(mixed $value): string =>
                $value instanceof DateTime ? $value->format('Y-m-d H:i:s') : (string)$value);
        $MembershipUser->method('getCycleEndDate')
            ->willReturn(new DateTime('2024-02-01 23:59:59'));
        $MembershipUser->method('getCycleBeginDate')
            ->willReturn(new DateTime('2024-01-01 00:00:00'));
        $MembershipUser->method('getNextCycleEndDate')
            ->willReturn(new DateTime('2024-03-01 23:59:59'));

        $data = $MembershipUser->getFrontendViewData();

        self::assertSame(8, $data['id']);
        self::assertSame('user-uuid', $data['userId']);
        self::assertSame('Membership', $data['membershipTitle']);
        self::assertSame('Description', $data['membershipShort']);
        self::assertSame('Content', $data['membershipContent']);
        self::assertTrue($data['cancelAllowed']);
        self::assertFalse($data['cancelled']);
    }

    public function testSendMailStopsIfUserHasNoEmail(): void
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getAttribute')->with('email')->willReturn('');
        $MembershipUser = $this->membershipUserMock(['getUserOrThrow']);
        $MembershipUser->method('getUserOrThrow')->willReturn($User);

        $MembershipUser->sendMail('Subject', '/does/not/matter.html');
        self::assertTrue(true);
    }

    public function testArchiveSetsAuditAttributes(): void
    {
        $MembershipUser = $this->membershipUserMock([
            'removeFromGroups',
            'addHistoryEntry',
            'setAttributes',
            'update'
        ]);
        $MembershipUser->expects(self::once())->method('removeFromGroups');
        $MembershipUser->expects(self::once())
            ->method('addHistoryEntry')
            ->with(Handler::HISTORY_TYPE_ARCHIVED, Handler::ARCHIVE_REASON_EXPIRED);
        $MembershipUser->expects(self::once())
            ->method('setAttributes')
            ->with(self::callback(static fn(array $data): bool =>
                $data['archived'] === 1
                && $data['archiveReason'] === Handler::ARCHIVE_REASON_EXPIRED
                && $data['archiveDate'] !== ''));
        $MembershipUser->expects(self::once())->method('update');

        $MembershipUser->archive(Handler::ARCHIVE_REASON_EXPIRED);
    }

    public function testBackendViewDataHandlesMissingUser(): void
    {
        $Membership = $this->createMock(Membership::class);
        $Membership->method('getId')->willReturn(4);
        $Membership->method('getTitle')->willReturn('Membership');
        $Membership->method('isInfinite')->willReturn(false);
        $MembershipUser = $this->membershipUserMock([
            'getId',
            'getUserId',
            'getUser',
            'getMembership',
            'getAttribute',
            'isArchived',
            'isCancelled',
            'getExtraData',
            'getContractId'
        ]);
        $MembershipUser->method('getId')->willReturn(8);
        $MembershipUser->method('getUserId')->willReturn('missing-user');
        $MembershipUser->method('getUser')->willReturn(null);
        $MembershipUser->method('getMembership')->willReturn($Membership);
        $MembershipUser->method('getAttribute')->willReturn(null);
        $MembershipUser->method('isArchived')->willReturn(true);
        $MembershipUser->method('isCancelled')->willReturn(false);
        $MembershipUser->method('getExtraData')->willReturn([]);
        $MembershipUser->method('getContractId')->willReturn(false);

        $data = $MembershipUser->getBackendViewData();

        self::assertSame(8, $data['id']);
        self::assertSame('-', $data['username']);
        self::assertSame('-', $data['firstname']);
        self::assertSame('-', $data['lastname']);
        self::assertSame('-', $data['fullName']);
        self::assertTrue($data['archived']);
        self::assertFalse($data['contractId']);
    }

    public function testVerificationHelpersUseInjectedServices(): void
    {
        $Now = new DateTimeImmutable();
        $CancelVerification = new LinkVerification(
            '00000000-0000-4000-8000-000000000001',
            'cancel',
            'code',
            $Now,
            $Now,
            0,
            'https://example.invalid/cancel'
        );
        $AbortVerification = new LinkVerification(
            '00000000-0000-4000-8000-000000000002',
            'abort',
            'code',
            $Now,
            $Now,
            0,
            'https://example.invalid/abort'
        );
        $Factory = $this->createMock(VerificationFactoryInterface::class);
        $Repository = $this->createMock(VerificationRepositoryInterface::class);
        $MembershipUser = $this->membershipUserMock(['getId']);
        $MembershipUser->method('getId')->willReturn(9);
        $this->setPrivateProperty($MembershipUser, 'id', 9, QUI\CRUD\Child::class);
        $this->setPrivateProperty($MembershipUser, 'verificationFactory', $Factory);
        $this->setPrivateProperty($MembershipUser, 'verificationRepository', $Repository);

        $Repository->method('findByIdentifier')->willReturnMap([
            ['quiqqer-memberships-users-cancel-9', $CancelVerification],
            ['quiqqer-memberships-users-cancel-abort-9', $AbortVerification]
        ]);

        self::assertSame($CancelVerification, $MembershipUser->getCancelVerificationPublic());
        self::assertSame($AbortVerification, $MembershipUser->getAbortCancelVerificationPublic());
    }

    /**
     * @param string[] $methods
     */
    private function membershipUserMock(array $methods): TestableMembershipUser
    {
        return $this->getMockBuilder(TestableMembershipUser::class)
            ->disableOriginalConstructor()
            ->onlyMethods($methods)
            ->getMock();
    }

    private function setPrivateProperty(
        object $object,
        string $property,
        mixed $value,
        string $class = MembershipUser::class
    ): void {
        $Property = new ReflectionProperty($class, $property);
        $Property->setValue($object, $value);
    }

    private function sessionUserDouble(): UserInterface
    {
        $User = $this->createMock(UserInterface::class);
        $User->method('getUUID')
            ->willReturn((string)QUI::getUserBySession()->getUUID());

        return $User;
    }

    private function createLinkVerification(string $identifier, string $url): LinkVerification
    {
        $Now = new DateTimeImmutable();

        return new LinkVerification(
            '00000000-0000-4000-8000-000000000003',
            $identifier,
            'code',
            $Now,
            $Now,
            0,
            $url
        );
    }
}
