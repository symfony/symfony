<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper;

use Symfony\Component\AssetMapper\Compiler\AssetCompilerInterface;
use Symfony\Component\AssetMapper\Compressor\CompressorInterface;
use Symfony\Component\AssetMapper\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\ConsoleBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Glob;

/**
 * Provides the asset mapper services.
 */
#[RequiredBundle(ServicesBundle::class)]
#[RequiredBundle(ConsoleBundle::class, ignoreOnInvalid: true)]
class AssetMapperBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemoveMissingDependenciesPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
            ->children()
                ->arrayNode('paths', 'path')
                    ->info('Directories that hold assets that should be in the mapper. Can be a simple array of an array of ["path/to/assets": "namespace"].')
                    ->example(['assets/'])
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('namespace')
                    ->acceptAndWrap(['string'])
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function ($v) {
                            $result = [];
                            foreach ($v as $key => $item) {
                                // "dir" => "namespace"
                                if (\is_string($key)) {
                                    $result[$key] = $item;

                                    continue;
                                }

                                if (\is_array($item)) {
                                    // $item = ["namespace" => "the/namespace", "value" => "the/dir"]
                                    $result[$item['value']] = $item['namespace'] ?? '';
                                } else {
                                    // $item = "the/dir"
                                    $result[$item] = '';
                                }
                            }

                            return $result;
                        })
                    ->end()
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('excluded_patterns', 'excluded_pattern')
                    ->info('Array of glob patterns of asset file paths that should not be in the asset mapper.')
                    ->prototype('scalar')->end()
                    ->example(['*/assets/build/*', '*/*_.scss'])
                ->end()
                ->booleanNode('exclude_dotfiles')
                    ->info('If true, any files starting with "." will be excluded from the asset mapper.')
                    ->defaultTrue()
                ->end()
                ->booleanNode('server')
                    ->info('If true, a "dev server" will return the assets from the public directory (true in "debug" mode only by default).')
                    ->defaultValue('%kernel.debug%')
                ->end()
                ->scalarNode('public_prefix')
                    ->info('The public path where the assets will be written to (and served from when "server" is true).')
                    ->defaultValue('/assets/')
                ->end()
                ->enumNode('missing_import_mode')
                    ->values(['strict', 'warn', 'ignore'])
                    ->info('Behavior if an asset cannot be found when imported from JavaScript or CSS files - e.g. "import \'./non-existent.js\'". "strict" means an exception is thrown, "warn" means a warning is logged, "ignore" means the import is left as-is.')
                    ->defaultValue('warn')
                ->end()
                ->arrayNode('extensions', 'extension')
                    ->info('Key-value pair of file extensions set to their mime type.')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('extension')
                    ->example(['.zip' => 'application/zip'])
                    ->prototype('scalar')->end()
                ->end()
                ->scalarNode('importmap_path')
                    ->info('The path of the importmap.php file.')
                    ->defaultValue('%kernel.project_dir%/importmap.php')
                ->end()
                ->scalarNode('importmap_polyfill')
                    ->info('The importmap name that will be used to load the polyfill. Set to false to disable.')
                    ->validate()
                        ->ifTrue()
                        ->thenInvalid('Invalid "importmap_polyfill" value. Must be either an importmap name or false.')
                    ->end()
                    ->defaultValue('es-module-shims')
                ->end()
                ->enumNode('importmap_entries')
                    ->info('Which entries end up in the rendered importmap: "all" of them, or only the ones "reachable" from the rendered entrypoints (their eager and lazy import chains) plus the polyfill.')
                    ->values(['all', 'reachable'])
                    ->defaultValue('all')
                ->end()
                ->arrayNode('importmap_script_attributes', 'importmap_script_attribute')
                    ->info('Key-value pair of attributes to add to script tags output for the importmap.')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('key')
                    ->example(['data-turbo-track' => 'reload'])
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('importmap_integrity_algorithms', 'importmap_integrity_algorithm')
                    ->info('Algorithms used to compute the integrity of the importmap resources.')
                    ->enumPrototype()->values(['sha256', 'sha384', 'sha512'])->end()
                    ->defaultValue([])
                ->end()
                ->scalarNode('vendor_dir')
                    ->info('The directory to store JavaScript vendors.')
                    ->defaultValue('%kernel.project_dir%/assets/vendor')
                ->end()
                ->integerNode('minimum_release_age')
                    ->info('Minimum age in seconds a package version must have to be considered when checking for updates (0 disables the check). Enabling it makes update checks download the full npm metadata document, which is larger than the abbreviated one.')
                    ->min(0)
                    ->defaultValue(0)
                ->end()
                ->arrayNode('precompress')
                    ->info('Precompress assets with Brotli, Zstandard and gzip.')
                    ->canBeEnabled()
                    ->children()
                        ->arrayNode('formats', 'format')
                            ->info('Array of formats to enable. "brotli", "zstandard" and "gzip" are supported. Defaults to all formats supported by the system. The entire list must be provided.')
                            ->prototype('scalar')->end()
                            ->performNoDeepMerging()
                            ->validate()
                                ->ifTrue(static fn ($v) => array_diff($v, ['brotli', 'zstandard', 'gzip']))
                                ->thenInvalid('Unsupported format: "brotli", "zstandard" and "gzip" are supported.')
                            ->end()
                        ->end()
                        ->arrayNode('extensions', 'extension')
                            ->info('Array of extensions to compress. The entire list must be provided, no merging occurs.')
                            ->prototype('scalar')->end()
                            ->performNoDeepMerging()
                            ->defaultValue(CompressorInterface::DEFAULT_EXTENSIONS)
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

        $configurator->import('Resources/config/asset_mapper.php');

        if (class_exists(Application::class)) {
            $configurator->import('Resources/config/console.php');
        }

        $container->registerForAutoconfiguration(AssetCompilerInterface::class)
            ->addTag('asset_mapper.compiler');

        $server = $container->getParameterBag()->resolveValue($config['server']);

        $container->getDefinition('asset_mapper.asset_package')
            ->replaceArgument(3, $server ? $config['public_prefix'] : null);

        $paths = $config['paths'];
        foreach ($container->getParameter('kernel.bundles_metadata') as $name => $bundle) {
            if ($container->fileExists($dir = $bundle['path'].'/Resources/public') || $container->fileExists($dir = $bundle['path'].'/public')) {
                $paths[$dir] = \sprintf('bundles/%s', preg_replace('/bundle$/', '', strtolower($name)));
            }
        }
        $excludedPathPatterns = [];
        foreach ($config['excluded_patterns'] as $path) {
            $excludedPathPatterns[] = Glob::toRegex($path, true, false);
        }

        $container->getDefinition('asset_mapper.repository')
            ->setArgument(0, $paths)
            ->setArgument(2, $excludedPathPatterns)
            ->setArgument(3, $config['exclude_dotfiles']);

        $container->getDefinition('asset_mapper.public_assets_path_resolver')
            ->setArgument(0, $config['public_prefix']);

        $publicDirectory = $this->getPublicDirectory($container);
        $publicAssetsDirectory = rtrim($publicDirectory.'/'.ltrim($config['public_prefix'], '/'), '/');
        $container->getDefinition('asset_mapper.local_public_assets_filesystem')
            ->setArgument(0, $publicDirectory)
        ;

        $container->getDefinition('asset_mapper.compiled_asset_mapper_config_reader')
            ->setArgument(0, $publicAssetsDirectory);

        if (!$server) {
            $container->removeDefinition('asset_mapper.dev_server_subscriber');
        } else {
            $container->getDefinition('asset_mapper.dev_server_subscriber')
                ->setArgument(1, $config['public_prefix'])
                ->setArgument(2, $config['extensions']);
        }

        $container->getDefinition('asset_mapper.compiler.css_asset_url_compiler')
            ->setArgument(0, $config['missing_import_mode']);

        $container->getDefinition('asset_mapper.compiler.javascript_import_path_compiler')
            ->setArgument(1, $config['missing_import_mode']);

        $container
            ->getDefinition('asset_mapper.importmap.remote_package_storage')
            ->replaceArgument(0, $config['vendor_dir'])
        ;
        $container
            ->getDefinition('asset_mapper.mapped_asset_factory')
            ->replaceArgument(2, $config['vendor_dir'])
        ;

        $container
            ->getDefinition('asset_mapper.importmap.generator')
            ->replaceArgument(3, $config['importmap_integrity_algorithms'])
            ->setArgument(4, $config['importmap_entries'])
        ;

        $container
            ->getDefinition('asset_mapper.importmap.config_reader')
            ->replaceArgument(0, $config['importmap_path'])
        ;

        $container
            ->getDefinition('asset_mapper.importmap.renderer')
            ->replaceArgument(3, $config['importmap_polyfill'])
            ->replaceArgument(4, $config['importmap_script_attributes'])
        ;
        $container
            ->getDefinition('asset_mapper.importmap.update_checker')
            ->replaceArgument(3, $config['minimum_release_age'])
        ;

        $compressors = [];
        foreach ($config['precompress']['formats'] as $format) {
            $compressors[$format] = new Reference("asset_mapper.compressor.$format");
        }

        $container->getDefinition('asset_mapper.compressor')->replaceArgument(0, $compressors ?: null);

        if ($config['precompress']['enabled']) {
            $container
                ->getDefinition('asset_mapper.local_public_assets_filesystem')
                ->addArgument(new Reference('asset_mapper.compressor'))
                ->addArgument($config['precompress']['extensions'])
            ;
        }
    }

    private function getPublicDirectory(ContainerBuilder $container): string
    {
        $projectDir = $container->getParameter('kernel.project_dir');
        $defaultPublicDir = $projectDir.'/public';

        $composerFilePath = $projectDir.'/composer.json';

        if (!file_exists($composerFilePath)) {
            return $defaultPublicDir;
        }

        $container->addResource(new FileResource($composerFilePath));
        $composerConfig = json_decode(new Filesystem()->readFile($composerFilePath), true, flags: \JSON_THROW_ON_ERROR);

        return isset($composerConfig['extra']['public-dir']) ? $projectDir.'/'.$composerConfig['extra']['public-dir'] : $defaultPublicDir;
    }
}
