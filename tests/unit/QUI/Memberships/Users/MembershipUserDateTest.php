<?php

namespace QUI\Memberships\Users;

use DateTime;
use PHPUnit\Framework\TestCase;
use QUI\Memberships\Handler as MembershipsHandler;
use QUI\Memberships\Membership;

class MembershipUserDateTest extends TestCase
{
    public function testNextCycleEndDateKeepsConfiguredTimezone(): void
    {
        $Config = MembershipsHandler::getConfig();
        $previousDurationMode = $Config->get('memberships', 'durationMode');
        $Config->set('memberships', 'durationMode', Handler::DURATION_MODE_DAY);

        try {
            $Membership = $this->createMock(Membership::class);
            $Membership->method('isInfinite')->willReturn(false);
            $Membership->method('getAttribute')
                ->with('duration')
                ->willReturn('1-month');
            $MembershipUser = $this->getMockBuilder(MembershipUser::class)
                ->disableOriginalConstructor()
                ->onlyMethods([
                    'getContract',
                    'getMembership',
                    'getNextCycleBeginDate'
                ])
                ->getMock();
            $MembershipUser->method('getContract')->willReturn(false);
            $MembershipUser->method('getMembership')->willReturn($Membership);
            $MembershipUser->method('getNextCycleBeginDate')
                ->willReturn(new DateTime('2024-02-01 00:00:00'));

            $NextCycleEndDate = $MembershipUser->getNextCycleEndDate();

            self::assertInstanceOf(DateTime::class, $NextCycleEndDate);
            self::assertSame(date_default_timezone_get(), $NextCycleEndDate->getTimezone()->getName());
            self::assertSame('2024-03-01 23:59:59', $NextCycleEndDate->format('Y-m-d H:i:s'));
        } finally {
            $Config->set('memberships', 'durationMode', $previousDurationMode);
        }
    }
}
