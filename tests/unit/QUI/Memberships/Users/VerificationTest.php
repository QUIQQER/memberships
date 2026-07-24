<?php

namespace QUI\Memberships\Users;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Memberships\Membership;
use QUI\Verification\Entity\AbstractVerification;
use QUI\Verification\Entity\LinkVerification;
use QUI\Verification\Enum\VerificationErrorReason;

require_once __DIR__ . '/TestableVerificationHandler.php';

class VerificationTest extends TestCase
{
    public function testAbstractHandlerDefaultsAndLoadsMembershipUser(): void
    {
        $MembershipUser = $this->createMock(MembershipUser::class);
        $MembershipUsers = $this->getMockBuilder(Handler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getChild'])
            ->getMock();
        $MembershipUsers->expects(self::once())
            ->method('getChild')
            ->with(42)
            ->willReturn($MembershipUser);
        $Verification = $this->createVerification(['membershipUserId' => 42]);
        $Handler = new TestableVerificationHandler($MembershipUsers);

        self::assertSame($MembershipUser, $Handler->getMembershipUserPublic($Verification));
        self::assertNull($Handler->getOnSuccessRedirectUrl($Verification));
        self::assertNull(
            $Handler->getOnErrorRedirectUrl($Verification, VerificationErrorReason::INVALID_CODE)
        );

        $Handler->onError($Verification, VerificationErrorReason::INVALID_CODE);
        self::assertTrue(true);
    }

    public function testCancelVerificationDurationSuccessAndErrorMessages(): void
    {
        $Verification = $this->createVerification();
        $Handler = new CancelVerification();

        self::assertSame(
            (int)Handler::getSetting('cancelDuration'),
            $Handler->getValidDuration($Verification)
        );
        self::assertSame(
            QUI::getLocale()->get('quiqqer/memberships', 'verification.cancel.success'),
            $Handler->getSuccessMessage($Verification)
        );

        foreach (self::cancelErrorMessageProvider() as [$reason, $localeKey]) {
            self::assertSame(
                QUI::getLocale()->get('quiqqer/memberships', $localeKey),
                $Handler->getErrorMessage($Verification, $reason)
            );
        }
    }

    public function testCancelVerificationSuccessConfirmsMembershipCancellation(): void
    {
        $MembershipUser = $this->createMock(MembershipUser::class);
        $MembershipUser->expects(self::once())
            ->method('setEditUser')
            ->with(QUI::getUsers()->getSystemUser());
        $MembershipUser->expects(self::once())->method('confirmManualCancel');
        $MembershipUsers = $this->membershipUsersHandlerFor($MembershipUser);
        $Verification = $this->createVerification(['membershipUserId' => 42]);

        (new CancelVerification($MembershipUsers))->onSuccess($Verification);
    }

    public function testAbortVerificationSuccessConfirmsCancellationAbort(): void
    {
        $MembershipUser = $this->createMock(MembershipUser::class);
        $MembershipUser->expects(self::once())
            ->method('setEditUser')
            ->with(QUI::getUsers()->getSystemUser());
        $MembershipUser->expects(self::once())->method('confirmAbortCancel');
        $MembershipUsers = $this->membershipUsersHandlerFor($MembershipUser);
        $Verification = $this->createVerification(['membershipUserId' => 42]);

        (new AbortCancelVerification($MembershipUsers))->onSuccess($Verification);
    }

    /**
     * @return iterable<array{VerificationErrorReason, string}>
     */
    public static function cancelErrorMessageProvider(): iterable
    {
        yield [
            VerificationErrorReason::EXPIRED,
            'verification.cancel.error.expired'
        ];
        yield [
            VerificationErrorReason::ALREADY_VERIFIED,
            'verification.cancel.error.already_cancelled'
        ];
        yield [
            VerificationErrorReason::INVALID_CODE,
            'verification.cancel.error.general'
        ];
    }

    #[DataProvider('abortErrorMessageProvider')]
    public function testAbortVerificationErrorMessages(
        VerificationErrorReason $reason,
        string $localeKey
    ): void {
        $Verification = $this->createVerification();
        $Handler = new AbortCancelVerification();

        self::assertSame(
            QUI::getLocale()->get('quiqqer/memberships', $localeKey),
            $Handler->getErrorMessage($Verification, $reason)
        );
    }

    /**
     * @return iterable<array{VerificationErrorReason, string}>
     */
    public static function abortErrorMessageProvider(): iterable
    {
        yield [
            VerificationErrorReason::EXPIRED,
            'verification.abortcancel.error.expired'
        ];
        yield [
            VerificationErrorReason::ALREADY_VERIFIED,
            'verification.abortcancel.error.already_verified'
        ];
        yield [
            VerificationErrorReason::INVALID_CODE,
            'verification.abortcancel.error.general'
        ];
    }

    public function testAbortVerificationUsesMembershipUserEndDate(): void
    {
        $endTimestamp = time() + 600;
        $MembershipUser = $this->createMock(MembershipUser::class);
        $MembershipUser->method('getAttribute')
            ->with('endDate')
            ->willReturn(date('Y-m-d H:i:s', $endTimestamp));
        $Handler = $this->createAbortHandler($MembershipUser);

        $duration = $Handler->getValidDuration($this->createVerification());

        self::assertNotNull($duration);
        self::assertGreaterThanOrEqual(9, $duration);
        self::assertLessThanOrEqual(10, $duration);
    }

    #[DataProvider('autoExtendProvider')]
    public function testAbortVerificationSuccessMessage(bool $autoExtend, string $localeKey): void
    {
        $Membership = $this->createMock(Membership::class);
        $Membership->method('isAutoExtend')->willReturn($autoExtend);
        $Membership->method('getTitle')->willReturn('Example');
        $MembershipUser = $this->createMock(MembershipUser::class);
        $MembershipUser->method('getMembership')->willReturn($Membership);
        $MembershipUser->method('getFrontendViewData')->willReturn(['endDate' => 'formatted']);
        $Handler = $this->createAbortHandler($MembershipUser);
        $Verification = $this->createVerification();

        self::assertSame(
            QUI::getLocale()->get(
                'quiqqer/memberships',
                $localeKey,
                ['endDate' => 'formatted', 'membershipTitle' => 'Example']
            ),
            $Handler->getSuccessMessage($Verification)
        );
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function autoExtendProvider(): iterable
    {
        yield 'auto extend' => [
            true,
            'verification.abortcancel.success.autoExtend'
        ];
        yield 'no auto extend' => [
            false,
            'verification.abortcancel.success.noAutoExtend'
        ];
    }

    private function createAbortHandler(MembershipUser $MembershipUser): AbortCancelVerification
    {
        $MembershipUsers = $this->getMockBuilder(Handler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getChild'])
            ->getMock();
        $MembershipUsers->method('getChild')->willReturn($MembershipUser);
        return new class ($MembershipUsers) extends AbortCancelVerification {
            public function __construct(Handler $MembershipUsers)
            {
                parent::__construct($MembershipUsers);
            }

            protected function getMembershipUser(LinkVerification $verification): MembershipUser
            {
                return $this->membershipUsersHandler->getChild(1);
            }
        };
    }

    private function membershipUsersHandlerFor(MembershipUser $MembershipUser): Handler
    {
        $MembershipUsers = $this->getMockBuilder(Handler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getChild'])
            ->getMock();
        $MembershipUsers->expects(self::once())
            ->method('getChild')
            ->with(42)
            ->willReturn($MembershipUser);

        return $MembershipUsers;
    }

    /**
     * @param array<string, mixed> $customData
     */
    private function createVerification(array $customData = []): LinkVerification
    {
        $Now = new DateTimeImmutable();

        return new LinkVerification(
            '00000000-0000-4000-8000-000000000001',
            'phpunit-memberships',
            'code',
            $Now,
            $Now,
            0,
            'https://example.invalid/verify',
            customData: $customData
        );
    }
}
