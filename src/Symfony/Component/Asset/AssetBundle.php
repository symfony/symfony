<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset;

use Symfony\Component\Asset\DependencyInjection\AssetsContextPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Provides the services that generate versioned asset URLs.
 */
#[RequiredBundle(ServicesBundle::class)]
class AssetBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AssetsContextPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
            ->children()
                ->booleanNode('strict_mode')
                    ->info('Throw an exception if an entry is missing from the manifest.json.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('version_strategy')->defaultNull()->end()
                ->scalarNode('version')->defaultNull()->end()
                ->scalarNode('version_format')->defaultValue('%%s?%%s')->end()
                ->scalarNode('json_manifest_path')->defaultNull()->end()
                ->scalarNode('base_path')->defaultValue('')->end()
                ->arrayNode('base_urls', 'base_url')
                    ->requiresAtLeastOneElement()
                    ->acceptAndWrap(['string'])
                    ->prototype('scalar')->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn ($v) => isset($v['version_strategy']) && isset($v['version']))
                ->thenInvalid('You cannot use both "version_strategy" and "version" at the same time under "assets".')
            ->end()
            ->validate()
                ->ifTrue(static fn ($v) => isset($v['version_strategy']) && isset($v['json_manifest_path']))
                ->thenInvalid('You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets".')
            ->end()
            ->validate()
                ->ifTrue(static fn ($v) => isset($v['version']) && isset($v['json_manifest_path']))
                ->thenInvalid('You cannot use both "version" and "json_manifest_path" at the same time under "assets".')
            ->end()
            ->children()
                ->arrayNode('packages', 'package')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->booleanNode('strict_mode')
                                ->info('Throw an exception if an entry is missing from the manifest.json.')
                                ->defaultFalse()
                            ->end()
                            ->scalarNode('version_strategy')->defaultNull()->end()
                            ->scalarNode('version')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(static fn ($v) => '' === $v ? null : $v)
                                ->end()
                            ->end()
                            ->scalarNode('version_format')->defaultNull()->end()
                            ->scalarNode('json_manifest_path')->defaultNull()->end()
                            ->scalarNode('base_path')->defaultValue('')->end()
                            ->arrayNode('base_urls', 'base_url')
                                ->requiresAtLeastOneElement()
                                ->acceptAndWrap(['string'])
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifTrue(static fn ($v) => isset($v['version_strategy']) && isset($v['version']))
                            ->thenInvalid('You cannot use both "version_strategy" and "version" at the same time under "assets" packages.')
                        ->end()
                        ->validate()
                            ->ifTrue(static fn ($v) => isset($v['version_strategy']) && isset($v['json_manifest_path']))
                            ->thenInvalid('You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets" packages.')
                        ->end()
                        ->validate()
                            ->ifTrue(static fn ($v) => isset($v['version']) && isset($v['json_manifest_path']))
                            ->thenInvalid('You cannot use both "version" and "json_manifest_path" at the same time under "assets" packages.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/assets.php');

        $container->registerForAutoconfiguration(PackageInterface::class)
            ->addTag('assets.package');

        if ($config['version_strategy']) {
            $defaultVersion = new Reference($config['version_strategy']);
        } else {
            $defaultVersion = $this->createVersion($container, $config['version'], $config['version_format'], $config['json_manifest_path'], '_default', $config['strict_mode']);
        }

        $defaultPackage = $this->createPackageDefinition($config['base_path'], $config['base_urls'], $defaultVersion);
        $container->setDefinition('assets._default_package', $defaultPackage);

        foreach ($config['packages'] as $name => $package) {
            if (null !== $package['version_strategy']) {
                $version = new Reference($package['version_strategy']);
            } elseif (!\array_key_exists('version', $package) && null === $package['json_manifest_path']) {
                // if neither version nor json_manifest_path are specified, use the default
                $version = $defaultVersion;
            } else {
                // let format fallback to main version_format
                $format = $package['version_format'] ?: $config['version_format'];
                $version = $package['version'] ?? null;
                $version = $this->createVersion($container, $version, $format, $package['json_manifest_path'], $name, $package['strict_mode']);
            }

            $packageDefinition = $this->createPackageDefinition($package['base_path'], $package['base_urls'], $version)
                ->addTag('assets.package', ['package' => $name]);
            $container->setDefinition('assets._package_'.$name, $packageDefinition);
            $container->registerAliasForArgument('assets._package_'.$name, PackageInterface::class, $name.'.package', $name);
        }
    }

    private function createPackageDefinition(?string $basePath, array $baseUrls, Reference $version): Definition
    {
        if ($basePath && $baseUrls) {
            throw new \LogicException('An asset package cannot have base URLs and base paths.');
        }

        $package = new ChildDefinition($baseUrls ? 'assets.url_package' : 'assets.path_package');
        $package
            ->replaceArgument(0, $baseUrls ?: $basePath)
            ->replaceArgument(1, $version)
        ;

        return $package;
    }

    private function createVersion(ContainerBuilder $container, ?string $version, ?string $format, ?string $jsonManifestPath, string $name, bool $strictMode): Reference
    {
        // Configuration prevents $version and $jsonManifestPath from being set
        if (null !== $version) {
            $def = new ChildDefinition('assets.static_version_strategy');
            $def
                ->replaceArgument(0, $version)
                ->replaceArgument(1, $format)
            ;
            $container->setDefinition('assets._version_'.$name, $def);

            return new Reference('assets._version_'.$name);
        }

        if (null !== $jsonManifestPath) {
            $def = new ChildDefinition('assets.json_manifest_version_strategy');
            $def->replaceArgument(0, $jsonManifestPath);
            $def->replaceArgument(2, $strictMode);
            $container->setDefinition('assets._version_'.$name, $def);

            return new Reference('assets._version_'.$name);
        }

        return new Reference('assets.empty_version_strategy');
    }
}
