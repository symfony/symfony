<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\AssetBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;

class AssetBundleTest extends TestCase
{
    private const PACKAGES = [
        'version' => 'SomeVersionScheme',
        'base_urls' => 'http://cdn.example.com',
        'version_format' => '%%s?version=%%s',
        'packages' => [
            'images_path' => ['base_path' => '/foo'],
            'images' => ['version' => '1.0.0', 'base_urls' => ['http://images1.example.com', 'http://images2.example.com']],
            'foo' => ['version' => '1.0.0', 'version_format' => '%%s-%%s'],
            'bar' => ['base_urls' => ['https://bar2.example.com']],
            'bar_version_strategy' => ['base_urls' => ['https://bar2.example.com'], 'version_strategy' => 'assets.custom_version_strategy'],
            'json_manifest_strategy' => ['json_manifest_path' => '/path/to/manifest.json'],
            'remote_manifest' => ['json_manifest_path' => 'https://cdn.example.com/manifest.json'],
            'var_manifest' => ['json_manifest_path' => '%var_json_manifest_path%'],
            'env_manifest' => ['json_manifest_path' => '%env(env_manifest)%'],
            'strict_manifest_strategy' => ['json_manifest_path' => '/path/to/manifest.json', 'strict_mode' => true],
        ],
    ];

    public function testTheDefaultPackageIsRegisteredWithoutConfiguration()
    {
        $container = $this->load([]);

        $this->assertTrue($container->hasDefinition('assets.packages'));
        $this->assertTrue($container->hasDefinition('assets._default_package'));
    }

    public function testNothingIsRegisteredWhenDisabled()
    {
        $container = $this->load(['enabled' => false]);

        $this->assertFalse($container->hasDefinition('assets.packages'));
    }

    public function testAssets()
    {
        $container = $this->load(self::PACKAGES, ['var_json_manifest_path' => 'https://cdn.example.com/manifest.json', 'env(env_manifest)' => 'https://cdn.example.com/manifest.json']);
        $packages = $container->getDefinition('assets.packages');

        // default package
        $defaultPackage = $container->getDefinition((string) $packages->getArgument(0));
        $this->assertUrlPackage($container, $defaultPackage, ['http://cdn.example.com'], 'SomeVersionScheme', '%%s?version=%%s');

        // packages
        $packageTags = $container->findTaggedServiceIds('assets.package');
        $this->assertCount(10, $packageTags);

        $packages = [];
        foreach ($packageTags as $serviceId => $tagAttributes) {
            $packages[$tagAttributes[0]['package']] = $serviceId;
        }

        $package = $container->getDefinition((string) $packages['images_path']);
        $this->assertPathPackage($container, $package, '/foo', 'SomeVersionScheme', '%%s?version=%%s');

        $package = $container->getDefinition((string) $packages['images']);
        $this->assertUrlPackage($container, $package, ['http://images1.example.com', 'http://images2.example.com'], '1.0.0', '%%s?version=%%s');

        $package = $container->getDefinition((string) $packages['foo']);
        $this->assertPathPackage($container, $package, '', '1.0.0', '%%s-%%s');

        $package = $container->getDefinition((string) $packages['bar']);
        $this->assertUrlPackage($container, $package, ['https://bar2.example.com'], 'SomeVersionScheme', '%%s?version=%%s');

        $package = $container->getDefinition((string) $packages['bar_version_strategy']);
        $this->assertEquals('assets.custom_version_strategy', (string) $package->getArgument(1));

        $package = $container->getDefinition((string) $packages['json_manifest_strategy']);
        $versionStrategy = $container->getDefinition((string) $package->getArgument(1));
        $this->assertEquals('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        $this->assertEquals('/path/to/manifest.json', $versionStrategy->getArgument(0));
        $this->assertFalse($versionStrategy->getArgument(2));

        $package = $container->getDefinition($packages['remote_manifest']);
        $versionStrategy = $container->getDefinition($package->getArgument(1));
        $this->assertSame('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        $this->assertSame('https://cdn.example.com/manifest.json', $versionStrategy->getArgument(0));

        $package = $container->getDefinition($packages['var_manifest']);
        $versionStrategy = $container->getDefinition($package->getArgument(1));
        $this->assertSame('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        $this->assertSame('https://cdn.example.com/manifest.json', $versionStrategy->getArgument(0));
        $this->assertFalse($versionStrategy->getArgument(2));

        $package = $container->getDefinition($packages['env_manifest']);
        $versionStrategy = $container->getDefinition($package->getArgument(1));
        $this->assertSame('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        $this->assertStringMatchesFormat('env_%s', $versionStrategy->getArgument(0));
        $this->assertFalse($versionStrategy->getArgument(2));

        $package = $container->getDefinition((string) $packages['strict_manifest_strategy']);
        $versionStrategy = $container->getDefinition((string) $package->getArgument(1));
        $this->assertEquals('assets.json_manifest_version_strategy', $versionStrategy->getParent());
        $this->assertEquals('/path/to/manifest.json', $versionStrategy->getArgument(0));
        $this->assertTrue($versionStrategy->getArgument(2));
    }

    public function testAssetsDefaultVersionStrategyAsService()
    {
        $container = $this->load(['version_strategy' => 'assets.custom_version_strategy', 'base_urls' => 'http://cdn.example.com']);
        $packages = $container->getDefinition('assets.packages');

        // default package
        $defaultPackage = $container->getDefinition((string) $packages->getArgument(0));
        $this->assertEquals('assets.custom_version_strategy', (string) $defaultPackage->getArgument(1));
    }

    private function assertPathPackage(ContainerBuilder $container, ChildDefinition $package, $basePath, $version, $format)
    {
        $this->assertEquals('assets.path_package', $package->getParent());
        $this->assertEquals($basePath, $package->getArgument(0));
        $this->assertVersionStrategy($container, $package->getArgument(1), $version, $format);
    }

    private function assertUrlPackage(ContainerBuilder $container, ChildDefinition $package, $baseUrls, $version, $format)
    {
        $this->assertEquals('assets.url_package', $package->getParent());
        $this->assertEquals($baseUrls, $package->getArgument(0));
        $this->assertVersionStrategy($container, $package->getArgument(1), $version, $format);
    }

    private function assertVersionStrategy(ContainerBuilder $container, Reference $reference, $version, $format)
    {
        $versionStrategy = $container->getDefinition((string) $reference);
        if (null === $version) {
            $this->assertEquals('assets.empty_version_strategy', (string) $reference);
        } else {
            $this->assertEquals('assets.static_version_strategy', $versionStrategy->getParent());
            $this->assertEquals($version, $versionStrategy->getArgument(0));
            $this->assertEquals($format, $versionStrategy->getArgument(1));
        }
    }

    public function testAssetsCanBeEnabled()
    {
        $config = $this->processConfig([]);

        $defaultConfig = [
            'enabled' => true,
            'version_strategy' => null,
            'version' => null,
            'version_format' => '%%s?%%s',
            'base_path' => '',
            'base_urls' => [],
            'packages' => [],
            'json_manifest_path' => null,
            'strict_mode' => false,
        ];

        $this->assertEquals($defaultConfig, $config);
    }

    #[DataProvider('provideValidAssetsPackageNameConfigurationTests')]
    public function testValidAssetsPackageNameConfiguration($packageName)
    {
        $config = $this->processConfig([
                    'packages' => [
                        $packageName => [],
                    ],
                ]);

        $this->assertArrayHasKey($packageName, $config['packages']);
    }

    public static function provideValidAssetsPackageNameConfigurationTests(): array
    {
        return [
            ['foobar'],
            ['foo-bar'],
            ['foo_bar'],
        ];
    }

    #[DataProvider('provideInvalidAssetConfigurationTests')]
    public function testInvalidAssetsConfiguration(array $assetConfig, $expectedMessage)
    {

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->processConfig($assetConfig);
    }

    public static function provideInvalidAssetConfigurationTests(): iterable
    {
        // helper to turn config into embedded package config
        $createPackageConfig = static fn (array $packageConfig) => [
            'base_urls' => '//example.com',
            'version' => 1,
            'packages' => [
                'foo' => $packageConfig,
            ],
        ];

        $config = [
            'version' => 1,
            'version_strategy' => 'foo',
        ];
        yield [$config, 'You cannot use both "version_strategy" and "version" at the same time under "assets".'];
        yield [$createPackageConfig($config), 'You cannot use both "version_strategy" and "version" at the same time under "assets" packages.'];

        $config = [
            'json_manifest_path' => '/foo.json',
            'version_strategy' => 'foo',
        ];
        yield [$config, 'You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets".'];
        yield [$createPackageConfig($config), 'You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets" packages.'];

        $config = [
            'json_manifest_path' => '/foo.json',
            'version' => '1',
        ];
        yield [$config, 'You cannot use both "version" and "json_manifest_path" at the same time under "assets".'];
        yield [$createPackageConfig($config), 'You cannot use both "version" and "json_manifest_path" at the same time under "assets" packages.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function processConfig(array $config): array
    {
        $extension = new AssetBundle()->getContainerExtension();

        return new Processor()->processConfiguration($extension->getConfiguration([], new ContainerBuilder()), [$config]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function load(array $config, array $parameters = []): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag(['kernel.debug' => false] + $parameters));
        $container->registerExtension(new AssetBundle()->getContainerExtension());
        $container->loadFromExtension('asset', $config);
        new MergeExtensionConfigurationPass()->process($container);

        return $container;
    }
}
