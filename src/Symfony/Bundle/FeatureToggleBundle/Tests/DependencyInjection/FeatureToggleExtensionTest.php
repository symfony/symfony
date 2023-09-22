<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FeatureToggleBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FeatureToggleBundle\DependencyInjection\Configuration;
use Symfony\Bundle\FeatureToggleBundle\DependencyInjection\FeatureToggleExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\FeatureToggle\Strategy\StrategyInterface;

/**
 * @covers \Symfony\Bundle\FeatureToggleBundle\DependencyInjection\FeatureToggleExtension
 *
 * @uses \Symfony\Component\DependencyInjection\ContainerBuilder
 * @uses \Symfony\Component\DependencyInjection\ParameterBag\ParameterBag
 *
 * @phpstan-import-type ConfigurationType from Configuration
 */
final class FeatureToggleExtensionTest extends TestCase
{
    /**
     * @return list<ConfigurationType>
     */
    public function getConfig(): array
    {
        return [
            [
                'strategies' => [
                    [
                        'name' => 'date.feature-strategy',
                        'type' => 'date',
                        'with' => ['since' => '-2 days'],
                    ],
                    [
                        'name' => 'env.feature-strategy',
                        'type' => 'env',
                        'with' => ['name' => 'SOME_ENV'],
                    ],
                    [
                        'name' => 'request_header.feature-strategy',
                        'type' => 'request_header',
                        'with' => ['name' => 'SOME-HEADER-NAME'],
                    ],
                    [
                        'name' => 'request_query.feature-strategy',
                        'type' => 'request_query',
                        'with' => ['name' => 'some_query_parameter'],
                    ],
                    [
                        'name' => 'request_attribute.feature-strategy',
                        'type' => 'request_attribute',
                        'with' => ['name' => 'some_request_attribute'],
                    ],
                    [
                        'name' => 'priority.feature-strategy',
                        'type' => 'priority',
                        'with' => ['strategies' => ['env.feature-strategy', 'grant.feature-strategy']],
                    ],
                    [
                        'name' => 'affirmative.feature-strategy',
                        'type' => 'affirmative',
                        'with' => ['strategies' => ['env.feature-strategy', 'grant.feature-strategy']],
                    ],
                    [
                        'name' => 'unanimous.feature-strategy',
                        'type' => 'unanimous',
                        'with' => ['strategies' => ['env.feature-strategy', 'grant.feature-strategy']],
                    ],
                    [
                        'name' => 'not.feature-strategy',
                        'type' => 'not',
                        'with' => ['strategy' => 'grant.feature-strategy'],
                    ],
                    [
                        'name' => 'grant.feature-strategy',
                        'type' => 'grant',
                    ],
                    [
                        'name' => 'deny.feature-strategy',
                        'type' => 'deny',
                    ],
                ],
                'features' => [
                ],
            ],
        ];
    }

    public function getContainerBuilder(): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag(['kernel.debug' => true]));
    }

    public function testStrategiesAreDefinedAsServicesAndTagged()
    {
        $extension = new FeatureToggleExtension();

        $containerBuilder = $this->getContainerBuilder();
        $extension->load($this->getConfig(), $containerBuilder);

        $expectedServiceIds = array_column($this->getConfig()[0]['strategies'], 'name', null);
        $serviceIds = $containerBuilder->getServiceIds();

        foreach ($expectedServiceIds as $expectedServiceId) {
            self::assertContains($expectedServiceId, $serviceIds);
            if (\in_array($expectedServiceId, $serviceIds, true)) {
                $serviceDefinition = $containerBuilder->getDefinition($expectedServiceId);

                self::assertTrue(
                    $serviceDefinition->hasTag('feature_toggle.feature_strategy'),
                    "'{$expectedServiceId}' does not have the tag.",
                );

                $tagConfigs = $serviceDefinition->getTag('feature_toggle.feature_strategy');
                self::assertCount(1, $tagConfigs);
            }
        }
    }

    public function testAutoconfigurationForInterfaces()
    {
        $extension = new FeatureToggleExtension();

        $containerBuilder = $this->getContainerBuilder();
        $extension->load($this->getConfig(), $containerBuilder);

        $registeredForAutoconfiguration = $containerBuilder->getAutoconfiguredInstanceof();

        self::assertArrayHasKey(
            StrategyInterface::class,
            $registeredForAutoconfiguration,
        );
        $node = $registeredForAutoconfiguration[StrategyInterface::class];
        $tags = $node->getTags();
        self::assertArrayHasKey('feature_toggle.feature_strategy', $tags);
    }
}
