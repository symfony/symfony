<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureToggle\Tests;

use Generator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\FeatureToggle\StrategyResult;
use function constant;

/**
 * @covers \Symfony\Component\FeatureToggle\StrategyResult
 */
final class StrategyResultTest extends TestCase
{
    public static function generateValidUseCases(): Generator
    {
        // Grant
        yield "grant should ignore fallback #1" => [
            StrategyResult::Grant->name,
            true,
            true,
        ];

        yield "grant should ignore fallback #2" => [
            StrategyResult::Grant->name,
            false,
            true,
        ];

        // Deny
        yield "deny should ignore fallback #1" => [
            StrategyResult::Deny->name,
            true,
            false,
        ];

        yield "deny should ignore fallback #2" => [
            StrategyResult::Deny->name,
            false,
            false,
        ];

        // Abstain
        yield "abstain should use fallback #1" => [
            StrategyResult::Abstain->name,
            true,
            true,
        ];

        yield "abstain should use fallback #2" => [
            StrategyResult::Abstain->name,
            false,
            false,
        ];
    }

    /**
     * @dataProvider generateValidUseCases
     */
    public function testItCorrectlyMatchesToBool(string $result, bool $fallback, bool $expectedResult): void
    {
        /** @var StrategyResult $strategyResult */
        $strategyResult = constant(StrategyResult::class.'::'.$result);

        self::assertSame($expectedResult, $strategyResult->isEnabled($fallback));
    }
}
