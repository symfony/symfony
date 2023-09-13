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
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @covers \Symfony\Bundle\FeatureToggleBundle\DependencyInjection\Configuration
 *
 * @uses \Symfony\Component\Config\Definition\Processor
 *
 * @phpstan-import-type ConfigurationType from Configuration
 * @phpstan-import-type ConfigurationStrategy from Configuration
 * @phpstan-import-type ConfigurationFeature from Configuration
 */
final class ConfigurationTest extends TestCase
{
    /**
     * @return ConfigurationType
     */
    public static function getBundleDefaultConfig(): array
    {
        return ['strategies' => [], 'features' => []];
    }

    public function testDefaultConfig(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(
            new Configuration(),
            [],
        );

        self::assertEquals(self::getBundleDefaultConfig(), $config);
    }

    public static function provideValidStrategyNameConfigurationTest(): \Generator
    {
        yield 'simple name' => ['foobar'];
        yield 'underscore name' => ['foo_bar'];
        yield 'dashed name' => ['foo-bar'];
    }

    /**
     * @dataProvider provideValidStrategyNameConfigurationTest
     */
    public function testValidStrategyNameConfiguration(string $strategyName): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(
            new Configuration(),
            [
                [
                    'strategies' => [
                        [
                            'name' => $strategyName,
                            'type' => 'provider-type',
                        ],
                    ],
                ],
            ],
        );

        self::assertArrayHasKey($strategyName, $config['strategies']);
    }

    public static function provideValidFeatureNameConfigurationTest(): \Generator
    {
        yield 'simple name' => ['foobar'];
        yield 'underscore name' => ['foo_bar'];
        yield 'dashed name' => ['foo-bar'];
    }

    /**
     * @dataProvider provideValidFeatureNameConfigurationTest
     */
    public function testValidFeatureNameConfiguration(string $featureName): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(
            new Configuration(),
            [
                [
                    'features' => [
                        [
                            'name' => $featureName,
                            'description' => "This is the description of {$featureName}",
                            'strategy' => 'fake-strategy',
                            'default' => false,
                        ],
                    ],
                ],
            ],
        );

        self::assertArrayHasKey($featureName, $config['features']);
    }

    public function testFeatureRequiresDescriptionKey(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('The child config "default" under "feature_toggle.features.some-feature" must be configured: Will be used as a fallback mechanism if the strategy return StrategyResult::Abstain.');

        $processor = new Processor();
        $processor->processConfiguration(
            new Configuration(),
            [
                [
                    'features' => [
                        [
                            'name' => 'some-feature',
                            'strategy' => 'fake-strategy',
                        ],
                    ],
                ],
            ],
        );
    }

    public function testFeatureRequiresStrategyKey(): void
    {
        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('The child config "strategy" under "feature_toggle.features.some-feature" must be configured: Strategy to be used for this feature. Can be one of "feature_toggle.strategies[].name" or a valid service id that implements StrategyInterface::class.');

        $processor = new Processor();
        $processor->processConfiguration(
            new Configuration(),
            [
                [
                    'features' => [
                        [
                            'name' => 'some-feature',
                            'default' => false,
                        ],
                    ],
                ],
            ],
        );
    }
}
