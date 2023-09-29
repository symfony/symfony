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
use Psr\Container\ContainerInterface;
use Symfony\Component\FeatureFlags\Feature;
use Symfony\Component\FeatureFlags\FeatureCollection;
use Symfony\Component\FeatureFlags\FeatureNotFoundException;
use Symfony\Component\FeatureFlags\Strategy\GrantStrategy;

/**
 * @covers \Symfony\Component\FeatureFlags\FeatureCollection
 *
 * @uses \Symfony\Component\FeatureFlags\Feature
 * @uses \Symfony\Component\FeatureFlags\Strategy\GrantStrategy
 */
final class FeatureCollectionTest extends TestCase
{
    public function testEnsureItListFeatureNames(): void
    {
        $featureCollection = FeatureCollection::withFeatures([
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
                strategy: new GrantStrategy()
            ),
        ]);

        self::assertIsIterable($featureCollection->names());
        self::assertCount(2, $featureCollection->names());
        self::assertSame(['fake-1', 'fake-2'], $featureCollection->names());
    }

    public function testEnsureItImplementsContainerInterface(): void
    {
        self::assertTrue(is_a(FeatureCollection::class, ContainerInterface::class, true));
    }

    public function testItCanFindTheFeature(): void
    {
        $featureFake1 = new Feature(
            name: 'fake-1',
            description: 'Fake description 1',
            default: true,
            strategy: new GrantStrategy()
        );

        $featureFake2 = new Feature(
            name: 'fake-2',
            description: 'Fake description 2',
            default: true,
            strategy: new GrantStrategy()
        );

        $featureCollection = FeatureCollection::withFeatures([$featureFake1, $featureFake2]);

        self::assertTrue($featureCollection->has('fake-1'));
        self::assertSame($featureFake1, $featureCollection->get('fake-1'));

        self::assertTrue($featureCollection->has('fake-2'));
        self::assertSame($featureFake2, $featureCollection->get('fake-2'));
    }

    public function testItThrowsWhenFeatureNotFound(): void
    {
        $featureCollection = FeatureCollection::withFeatures([]);

        self::assertFalse($featureCollection->has('not-found-1'));

        self::expectException(FeatureNotFoundException::class);
        $featureCollection->get('not-found-1');
    }
}
