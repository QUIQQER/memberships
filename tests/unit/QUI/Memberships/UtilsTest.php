<?php

namespace QUI\Memberships;

use DateTime;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    public function testClearJsonStringAndNestedArrays(): void
    {
        self::assertSame('x', Utils::clearJSONString('<b>x</b>'));
        self::assertSame(
            [
                'html' => 'x',
                'json' => '{"key":"a&amp;b"}',
                'nested' => ['path' => 'foo'],
                'number' => 3
            ],
            Utils::clearArrayWithJSON([
                'html' => '<b>x</b>',
                'json' => '{"key":"a&b"}',
                'nested' => ['path' => '../foo'],
                'number' => 3
            ])
        );
    }

    public function testGetFormattedTimestampSupportsAllInputForms(): void
    {
        self::assertSame(
            '2024-01-02 03:04:05',
            Utils::getFormattedTimestamp(new DateTime('2024-01-02 03:04:05'))
        );

        $timestamp = strtotime('2024-01-02 03:04:05');

        self::assertIsInt($timestamp);
        self::assertSame(date('Y-m-d H:i:s', $timestamp), Utils::getFormattedTimestamp($timestamp));
        self::assertSame(date('Y-m-d H:i:s', $timestamp), Utils::getFormattedTimestamp((string)$timestamp));
        self::assertSame(
            date('Y-m-d H:i:s', $timestamp),
            Utils::getFormattedTimestamp('2024-01-02 03:04:05')
        );
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            Utils::getFormattedTimestamp()
        );
    }

    public function testGetFormattedTimestampRejectsInvalidDateString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Utils::getFormattedTimestamp('definitely-not-a-date');
    }

    #[DataProvider('durationProvider')]
    public function testParseIntervalFromDuration(string $duration, string $expected): void
    {
        $Interval = Utils::parseIntervalFromDuration($duration);

        self::assertNotFalse($Interval);
        self::assertSame($expected, $Interval->format('%y:%m:%d'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function durationProvider(): iterable
    {
        yield 'week' => ['1-week', '0:0:7'];
        yield 'month' => ['2-month', '0:2:0'];
        yield 'year' => ['3-year', '3:0:0'];
        yield 'day' => ['4-day', '0:0:4'];
        yield 'unknown period defaults to days' => ['5-unknown', '0:0:5'];
    }

    public function testParseIntervalFromEmptyDurationReturnsFalse(): void
    {
        self::assertFalse(Utils::parseIntervalFromDuration(''));
    }

    public function testPackageHelpersReturnConsistentResults(): void
    {
        $packages = Utils::getInstalledMembershipPackages();

        self::assertSame(array_values(array_unique($packages)), $packages);
        self::assertSame(
            Utils::isQuiqqerProductsInstalled(),
            in_array('quiqqer/products', $packages, true)
        );
        self::assertSame(
            Utils::isQuiqqerErpPlansInstalled(),
            in_array('quiqqer/erp-plans', $packages, true)
        );
        self::assertSame(
            Utils::isQuiqqerContractsInstalled(),
            in_array('quiqqer/contracts', $packages, true)
        );
    }
}
