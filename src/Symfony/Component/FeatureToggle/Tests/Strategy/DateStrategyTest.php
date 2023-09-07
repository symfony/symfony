<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureToggle\Tests\Strategy;

use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\FeatureToggle\Strategy\DateStrategy;
use Symfony\Component\FeatureToggle\StrategyResult;

/**
 * @covers \Symfony\Component\FeatureToggle\Strategy\DateStrategy
 */
final class DateStrategyTest extends TestCase
{
    private static ClockInterface $nowClock;

    private static function generateClock(\DateTimeImmutable|null $now = null): ClockInterface
    {
        $now = $now ?? \DateTimeImmutable::createFromFormat('!d/m/Y', (new \DateTimeImmutable())->format('d/m/Y'));

        return new class($now) implements ClockInterface {
            public function __construct(private \DateTimeImmutable $now)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return $this->now;
            }
        };
    }

    public static function setUpBeforeClass(): void
    {
        self::$nowClock = self::generateClock();
    }

    public function testItRequiresAtLeastOneDate(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Either from or until must be provided.');

        new DateStrategy(self::generateClock());
    }

    public static function generateValidDates(): \Generator
    {
        $now = self::generateClock()->now();

        // from + no until
        yield '[-2 days;∞' => [
            'expected' => StrategyResult::Grant,
            'from' => $now->modify('-2 days'),
            'until' => null,
            'includeFrom' => true,
            'includeUntil' => false,
        ];

        yield ']-2 days;∞' => [
            'expected' => StrategyResult::Grant,
            'from' => $now->modify('-2 days'),
            'until' => null,
            'includeFrom' => false,
            'includeUntil' => false,
        ];

        yield '[now;∞' => [
            'expected' => StrategyResult::Grant,
            'from' => $now,
            'until' => null,
            'includeFrom' => true,
            'includeUntil' => false,
        ];

        yield ']now;∞' => [
            'expected' => StrategyResult::Deny,
            'from' => $now,
            'until' => null,
            'includeFrom' => false,
            'includeUntil' => false,
        ];

        yield '[+2 days;∞' => [
            'expected' => StrategyResult::Deny,
            'from' => $now->modify('+2 days'),
            'until' => null,
            'includeFrom' => true,
            'includeUntil' => false,
        ];

        yield ']+2 days;∞' => [
            'expected' => StrategyResult::Deny,
            'from' => $now->modify('+2 days'),
            'until' => null,
            'includeFrom' => false,
            'includeUntil' => false,
        ];

        // no from + until
        yield '∞;-2 days]' => [
            'expected' => StrategyResult::Deny,
            'from' => null,
            'until' => $now->modify('-2 days'),
            'includeFrom' => false,
            'includeUntil' => true,
        ];

        yield '∞;-2 days[' => [
            'expected' => StrategyResult::Deny,
            'from' => null,
            'until' => $now->modify('-2 days'),
            'includeFrom' => false,
            'includeUntil' => false,
        ];

        yield '∞;now]' => [
            'expected' => StrategyResult::Grant,
            'from' => null,
            'until' => $now,
            'includeFrom' => false,
            'includeUntil' => true,
        ];

        yield '∞;now[' => [
            'expected' => StrategyResult::Deny,
            'from' => null,
            'until' => $now,
            'includeFrom' => false,
            'includeUntil' => false,
        ];

        yield '∞;+2 days]' => [
            'expected' => StrategyResult::Grant,
            'from' => null,
            'until' => $now->modify('+2 days'),
            'includeFrom' => false,
            'includeUntil' => true,
        ];

        yield '∞;+2 days[' => [
            'expected' => StrategyResult::Grant,
            'from' => null,
            'until' => $now->modify('+2 days'),
            'includeFrom' => false,
            'includeUntil' => false,
        ];

        // from + until
        yield '[-2 days;-1 days]' => [
            'expected' => StrategyResult::Deny,
            'from' => $now->modify('-2 days'),
            'until' => $now->modify('-1 days'),
            'includeFrom' => true,
            'includeUntil' => true,
        ];

        yield '[-2 days;-1 days[' => [
            'expected' => StrategyResult::Deny,
            'from' => $now->modify('-2 days'),
            'until' => $now->modify('-1 days'),
            'includeFrom' => true,
            'includeUntil' => false,
        ];

        yield ']-2 days;-1 days[' => [
            'expected' => StrategyResult::Deny,
            'from' => $now->modify('-2 days'),
            'until' => $now->modify('-1 days'),
            'includeFrom' => false,
            'includeUntil' => false,
        ];

        yield ']-2 days;-1 days]' => [
            'expected' => StrategyResult::Deny,
            'from' => $now->modify('-2 days'),
            'until' => $now->modify('-1 days'),
            'includeFrom' => false,
            'includeUntil' => true,
        ];

        yield '[-2 days;+3 days]' => [
            'expected' => StrategyResult::Grant,
            'from' => $now->modify('-2 days'),
            'until' => $now->modify('+3 days'),
            'includeFrom' => true,
            'includeUntil' => true,
        ];

        yield '[-2 days;+3 days[' => [
            'expected' => StrategyResult::Grant,
            'from' => $now->modify('-2 days'),
            'until' => $now->modify('+3 days'),
            'includeFrom' => true,
            'includeUntil' => false,
        ];

        yield ']-2 days;+3 days[' => [
            'expected' => StrategyResult::Grant,
            'from' => $now->modify('-2 days'),
            'until' => $now->modify('+3 days'),
            'includeFrom' => false,
            'includeUntil' => false,
        ];

        yield ']-2 days;+3 days]' => [
            'expected' => StrategyResult::Grant,
            'from' => $now->modify('-2 days'),
            'until' => $now->modify('+3 days'),
            'includeFrom' => false,
            'includeUntil' => true,
        ];

        yield '[+1 days;+2 days]' => [
            'expected' => StrategyResult::Deny,
            'from' => $now->modify('+1 days'),
            'until' => $now->modify('+2 days'),
            'includeFrom' => true,
            'includeUntil' => true,
        ];

        yield '[+1 days;+2 days[' => [
            'expected' => StrategyResult::Deny,
            'from' => $now->modify('+1 days'),
            'until' => $now->modify('+2 days'),
            'includeFrom' => true,
            'includeUntil' => false,
        ];

        yield ']+1 days;+2 days[' => [
            'expected' => StrategyResult::Deny,
            'from' => $now->modify('+1 days'),
            'until' => $now->modify('+2 days'),
            'includeFrom' => false,
            'includeUntil' => false,
        ];

        yield ']+1 days;+2 days]' => [
            'expected' => StrategyResult::Deny,
            'from' => $now->modify('+1 days'),
            'until' => $now->modify('+2 days'),
            'includeFrom' => false,
            'includeUntil' => true,
        ];
    }

    /**
     * @dataProvider generateValidDates
     */
    public function testItComputeDatesCorrectly(
        StrategyResult $expected,
        \DateTimeImmutable|null $from = null,
        \DateTimeImmutable|null $until = null,
        bool $includeFrom = true,
        bool $includeUntil = true,
    ): void {
        $dateStrategy = new DateStrategy(self::$nowClock, $from, $until, $includeFrom, $includeUntil);

        self::assertSame($expected, $dateStrategy->compute());
    }
}
