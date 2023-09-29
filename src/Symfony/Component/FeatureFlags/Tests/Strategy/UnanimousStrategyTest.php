<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlags\Tests\Strategy;

use Symfony\Component\FeatureFlags\Strategy\StrategyInterface;
use Symfony\Component\FeatureFlags\Strategy\UnanimousStrategy;
use Symfony\Component\FeatureFlags\StrategyResult;

/**
 * @covers \Symfony\Component\FeatureFlags\Strategy\UnanimousStrategy
 */
final class UnanimousStrategyTest extends AbstractOuterStrategiesTestCase
{
    public static function generatesValidStrategies(): \Generator
    {
        yield 'no strategies' => [
            [],
            StrategyResult::Abstain,
        ];

        yield 'if all abstain' => [
            [
                self::generateStrategy(StrategyResult::Abstain),
                self::generateStrategy(StrategyResult::Abstain),
                self::generateStrategy(StrategyResult::Abstain),
            ],
            StrategyResult::Abstain,
        ];

        yield 'if one denies after only abstain results' => [
            [
                self::generateStrategy(StrategyResult::Abstain),
                self::generateStrategy(StrategyResult::Abstain),
                self::generateStrategy(StrategyResult::Deny),
            ],
            StrategyResult::Deny,
        ];

        yield 'if one grants after only abstain results' => [
            [
                self::generateStrategy(StrategyResult::Abstain),
                self::generateStrategy(StrategyResult::Abstain),
                self::generateStrategy(StrategyResult::Grant),
            ],
            StrategyResult::Grant,
        ];

        yield 'if one grants after at least one Deny' => [
            [
                self::generateStrategy(StrategyResult::Abstain),
                self::generateStrategy(StrategyResult::Deny),
                self::generateStrategy(StrategyResult::Grant),
            ],
            StrategyResult::Deny,
        ];

        yield 'if one denies after at least one grant' => [
            [
                self::generateStrategy(StrategyResult::Abstain),
                self::generateStrategy(StrategyResult::Grant),
                self::generateStrategy(StrategyResult::Deny),
            ],
            StrategyResult::Deny,
        ];
    }

    /**
     * @dataProvider generatesValidStrategies
     *
     * @param iterable<StrategyInterface> $strategies
     */
    public function testItComputesCorrectly(iterable $strategies, StrategyResult $expected): void
    {
        $affirmativeStrategy = new UnanimousStrategy($strategies);

        self::assertSame($expected, $affirmativeStrategy->compute());
    }
}
