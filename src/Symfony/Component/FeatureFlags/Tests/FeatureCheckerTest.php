<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlags\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\FeatureFlags\Feature;
use Symfony\Component\FeatureFlags\FeatureChecker;
use Symfony\Component\FeatureFlags\FeatureCollection;
use Symfony\Component\FeatureFlags\Strategy\DenyStrategy;
use Symfony\Component\FeatureFlags\Strategy\GrantStrategy;
use Symfony\Component\FeatureFlags\Strategy\StrategyInterface;
use Symfony\Component\FeatureFlags\StrategyResult;

/**
 * @covers \Symfony\Component\FeatureFlags\FeatureChecker
 *
 * @uses \Symfony\Component\FeatureFlags\FeatureCollection
 * @uses \Symfony\Component\FeatureFlags\Feature
 * @uses \Symfony\Component\FeatureFlags\Strategy\GrantStrategy
 * @uses \Symfony\Component\FeatureFlags\Strategy\DenyStrategy
 */
final class FeatureCheckerTest extends TestCase
{
    public function testItCorrectlyCheckTheFeaturesEvenIfNotFound(): void
    {
        $featureChecker = new FeatureChecker(
            FeatureCollection::withFeatures([]),
            true
        );

        self::assertTrue($featureChecker->isEnabled('not-found-1'));

        $featureChecker = new FeatureChecker(
            FeatureCollection::withFeatures([
                new Feature(
                    name: 'fake-1',
                    description: 'Fake description 1',
                    default: true,
                    strategy: new GrantStrategy()
                ),
                new Feature(
                    name: 'fake-2',
                    description: 'Fake description 2',
                    default: true,
                    strategy: new DenyStrategy()
                ),
                new Feature(
                    name: 'fake-3',
                    description: 'Fake description 3',
                    default: false,
                    strategy: new class implements StrategyInterface {
                        public function compute(): StrategyResult
                        {
                            return StrategyResult::Abstain;
                        }
                    }
                ),
            ]),
            false
        );

        self::assertFalse($featureChecker->isEnabled('not-found-1'));
        self::assertTrue($featureChecker->isEnabled('fake-1'));
        self::assertFalse($featureChecker->isEnabled('fake-2'));
        self::assertFalse($featureChecker->isEnabled('fake-3'));
    }
}
