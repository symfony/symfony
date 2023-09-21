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

use Symfony\Component\FeatureToggle\Strategy\AffirmativeStrategy;
use Symfony\Component\FeatureToggle\Strategy\StrategyInterface;
use Symfony\Component\FeatureToggle\StrategyResult;
use function is_a;

/**
 * @covers \Symfony\Component\FeatureToggle\Strategy\AffirmativeStrategy
 */
final class AffirmativeStrategyTest extends AbstractOuterStrategiesTestCase
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
            StrategyResult::Grant,
        ];

        yield 'if one denies after at least one grant' => [
            [
                self::generateStrategy(StrategyResult::Abstain),
                self::generateStrategy(StrategyResult::Grant),
                self::generateStrategy(StrategyResult::Deny),
            ],
            StrategyResult::Grant,
        ];
    }

    /**
     * @dataProvider generatesValidStrategies
     *
     * @param iterable<StrategyInterface> $strategies
     */
    public function testItComputesCorrectly(iterable $strategies, StrategyResult $expected): void
    {
        $affirmativeStrategy = new AffirmativeStrategy($strategies);

        self::assertSame($expected, $affirmativeStrategy->compute());
    }
}
