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

use PHPUnit\Framework\TestCase;
use Symfony\Component\FeatureToggle\Feature;
use Symfony\Component\FeatureToggle\Strategy\StrategyInterface;
use Symfony\Component\FeatureToggle\StrategyResult;

/**
 * @covers \Symfony\Component\FeatureToggle\Feature
 *
 * @uses \Symfony\Component\FeatureToggle\StrategyResult
 */
final class FeatureTest extends TestCase
{
    public function testItCanBeInstantiated(): void
    {
        new Feature(
            name: 'fake',
            description: 'Fake description',
            default: false,
            strategy: new class implements StrategyInterface {
                public function compute(): StrategyResult
                {
                    return StrategyResult::Abstain;
                }
            }
        );

        self::addToAssertionCount(1);
    }

    public static function generateValidStrategy(): \Generator
    {
        // Grant
        yield "grant and default 'true'" => [
            StrategyResult::Grant,
            true,
            true,
        ];

        yield "grant and default 'false'" => [
            StrategyResult::Grant,
            false,
            true,
        ];

        // Deny
        yield "deny and default 'true'" => [
            StrategyResult::Deny,
            true,
            false,
        ];

        yield "deny and default 'false'" => [
            StrategyResult::Deny,
            false,
            false,
        ];

        // Abstain
        yield "abstain and default 'true'" => [
            StrategyResult::Abstain,
            true,
            true,
        ];

        yield "abstain and default 'false'" => [
            StrategyResult::Abstain,
            false,
            false,
        ];
    }

    /**
     * @dataProvider generateValidStrategy
     */
    public function testItCorrectlyComputeAndHandlesDefault(StrategyResult $strategyResult, bool $default, bool $expectedResult): void
    {
        $strategy = self::createMock(StrategyInterface::class);

        $strategy
            ->expects(self::once())
            ->method('compute')
            ->willReturn($strategyResult)
        ;

        $feature = new Feature(
            name: 'fake',
            description: 'Fake description',
            default: $default,
            strategy: $strategy
        );

        self::assertSame($expectedResult, $feature->isEnabled());
    }
}
