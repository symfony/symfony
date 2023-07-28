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
use Psr\Container\ContainerInterface;
use Symfony\Component\FeatureToggle\Feature;
use Symfony\Component\FeatureToggle\FeatureCollection;
use Symfony\Component\FeatureToggle\FeatureNotFoundException;
use Symfony\Component\FeatureToggle\Strategy\GrantStrategy;
use function is_a;

/**
 * @covers \Symfony\Component\FeatureToggle\FeatureCollection
 *
 * @uses \Symfony\Component\FeatureToggle\Feature
 * @uses \Symfony\Component\FeatureToggle\Strategy\GrantStrategy
 */
final class FeatureCollectionTest extends TestCase
{
    public function testEnsureItIsIterable(): void
    {
        $featureCollection = new FeatureCollection([
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

        self::assertIsIterable($featureCollection);
        self::assertCount(2, $featureCollection);
    }

    public function testEnsureItImplementsContainerInterface(): void
    {
        self::assertTrue(is_a(FeatureCollection::class, ContainerInterface::class, true));
    }

    public function testEnsureItIsMergeableWithDifferentTypesOfIterable(): void
    {
        $featureCollection = new FeatureCollection([
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

        $featureCollection->withFeatures(function (): Generator {
            yield new Feature(
                name: 'fake-3',
                description: 'Fake description 3',
                default: true,
                strategy: new GrantStrategy()
            );
        });

        self::assertCount(3, $featureCollection);

        $featureCollection->withFeatures([new Feature(
            name: 'fake-4',
            description: 'Fake description 4',
            default: true,
            strategy: new GrantStrategy()
        )]);

        self::assertCount(4, $featureCollection);
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

        $featureCollection = new FeatureCollection([$featureFake1, $featureFake2]);

        self::assertTrue($featureCollection->has('fake-1'));
        self::assertSame($featureFake1, $featureCollection->get('fake-1'));

        self::assertTrue($featureCollection->has('fake-2'));
        self::assertSame($featureFake2, $featureCollection->get('fake-2'));
    }

    public function testItThrowsWhenFeatureNotFound(): void
    {
        $featureCollection = new FeatureCollection([]);

        self::assertFalse($featureCollection->has('not-found-1'));

        self::expectException(FeatureNotFoundException::class);
        $featureCollection->get('not-found-1');
    }
}
