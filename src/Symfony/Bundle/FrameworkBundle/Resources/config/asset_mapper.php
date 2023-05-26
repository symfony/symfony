<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\AssetMapper\AssetMapperCompiler;
use Symfony\Component\AssetMapper\AssetMapperDevServerSubscriber;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\AssetMapperRepository;
use Symfony\Component\AssetMapper\Command\AssetMapperCompileCommand;
use Symfony\Component\AssetMapper\Command\DebugAssetMapperCommand;
use Symfony\Component\AssetMapper\Command\ImportMapExportCommand;
use Symfony\Component\AssetMapper\Command\ImportMapRemoveCommand;
use Symfony\Component\AssetMapper\Command\ImportMapRequireCommand;
use Symfony\Component\AssetMapper\Command\ImportMapUpdateCommand;
use Symfony\Component\AssetMapper\Compiler\CssAssetUrlCompiler;
use Symfony\Component\AssetMapper\Compiler\JavaScriptImportPathCompiler;
use Symfony\Component\AssetMapper\Compiler\SourceMappingUrlsCompiler;
use Symfony\Component\AssetMapper\Factory\CachedMappedAssetFactory;
use Symfony\Component\AssetMapper\Factory\MappedAssetFactory;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;
use Symfony\Component\AssetMapper\ImportMap\Resolver\JsDelivrEsmResolver;
use Symfony\Component\AssetMapper\ImportMap\Resolver\JspmResolver;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolver;
use Symfony\Component\AssetMapper\MapperAwareAssetPackage;
use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolver;
use Symfony\Component\HttpKernel\Event\RequestEvent;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('asset_mapper', AssetMapper::class)
            ->args([
                service('asset_mapper.repository'),
                service('asset_mapper.mapped_asset_factory'),
                service('asset_mapper.public_assets_path_resolver'),
            ])
        ->alias(AssetMapperInterface::class, 'asset_mapper')

        ->set('asset_mapper.mapped_asset_factory', MappedAssetFactory::class)
            ->args([
                service('asset_mapper.public_assets_path_resolver'),
                service('asset_mapper_compiler'),
            ])

        ->set('asset_mapper.cached_mapped_asset_factory', CachedMappedAssetFactory::class)
            ->args([
                service('.inner'),
                param('kernel.cache_dir').'/asset_mapper',
                param('kernel.debug'),
            ])
            ->decorate('asset_mapper.mapped_asset_factory')

        ->set('asset_mapper.repository', AssetMapperRepository::class)
            ->args([
                abstract_arg('array of asset mapper paths'),
                param('kernel.project_dir'),
                abstract_arg('array of excluded path patterns'),
            ])

        ->set('asset_mapper.public_assets_path_resolver', PublicAssetsPathResolver::class)
            ->args([
                param('kernel.project_dir'),
                abstract_arg('asset public prefix'),
                abstract_arg('public directory name'),
            ])

        ->set('asset_mapper.asset_package', MapperAwareAssetPackage::class)
            ->decorate('assets._default_package')
            ->args([
                service('.inner'),
                service('asset_mapper'),
            ])

        ->set('asset_mapper.dev_server_subscriber', AssetMapperDevServerSubscriber::class)
            ->args([
                service('asset_mapper'),
                abstract_arg('asset public prefix'),
                abstract_arg('extensions map'),
                service('cache.asset_mapper'),
            ])
            ->tag('kernel.event_subscriber', ['event' => RequestEvent::class])

        ->set('asset_mapper.command.compile', AssetMapperCompileCommand::class)
            ->args([
                service('asset_mapper.public_assets_path_resolver'),
                service('asset_mapper'),
                service('asset_mapper.importmap.manager'),
                service('filesystem'),
                param('kernel.project_dir'),
                abstract_arg('public directory name'),
                param('kernel.debug'),
            ])
            ->tag('console.command')

            ->set('asset_mapper.command.debug', DebugAssetMapperCommand::class)
                ->args([
                    service('asset_mapper'),
                    service('asset_mapper.repository'),
                    param('kernel.project_dir'),
                ])
                ->tag('console.command')

        ->set('asset_mapper_compiler', AssetMapperCompiler::class)
            ->args([
                tagged_iterator('asset_mapper.compiler'),
                service_closure('asset_mapper'),
            ])

        ->set('asset_mapper.compiler.css_asset_url_compiler', CssAssetUrlCompiler::class)
            ->args([
                abstract_arg('missing import mode'),
                service('logger'),
            ])
            ->tag('asset_mapper.compiler')
            ->tag('monolog.logger', ['channel' => 'asset_mapper'])

        ->set('asset_mapper.compiler.source_mapping_urls_compiler', SourceMappingUrlsCompiler::class)
            ->tag('asset_mapper.compiler')

        ->set('asset_mapper.compiler.javascript_import_path_compiler', JavaScriptImportPathCompiler::class)
            ->args([
                abstract_arg('missing import mode'),
                service('logger'),
            ])
            ->tag('asset_mapper.compiler')
            ->tag('monolog.logger', ['channel' => 'asset_mapper'])

        ->set('asset_mapper.importmap.manager', ImportMapManager::class)
            ->args([
                service('asset_mapper'),
                service('asset_mapper.public_assets_path_resolver'),
                abstract_arg('importmap.php path'),
                abstract_arg('vendor directory'),
                service('asset_mapper.importmap.resolver'),
            ])
        ->alias(ImportMapManager::class, 'asset_mapper.importmap.manager')

        ->set('asset_mapper.importmap.resolver', PackageResolver::class)
            ->args([
                abstract_arg('provider'),
                tagged_locator('asset_mapper.importmap.resolver'),
            ])

        ->set('asset_mapper.importmap.resolver.jsdelivr_esm', JsDelivrEsmResolver::class)
            ->args([service('http_client')])
            ->tag('asset_mapper.importmap.resolver', ['resolver' => ImportMapManager::PROVIDER_JSDELIVR_ESM])

        ->set('asset_mapper.importmap.resolver.jspm', JspmResolver::class)
            ->args([service('http_client'), ImportMapManager::PROVIDER_JSPM])
            ->tag('asset_mapper.importmap.resolver', ['resolver' => ImportMapManager::PROVIDER_JSPM])

        ->set('asset_mapper.importmap.resolver.jspm_system', JspmResolver::class)
            ->args([service('http_client'), ImportMapManager::PROVIDER_JSPM_SYSTEM])
            ->tag('asset_mapper.importmap.resolver', ['resolver' => ImportMapManager::PROVIDER_JSPM_SYSTEM])

        ->set('asset_mapper.importmap.resolver.skypack', JspmResolver::class)
            ->args([service('http_client'), ImportMapManager::PROVIDER_SKYPACK])
            ->tag('asset_mapper.importmap.resolver', ['resolver' => ImportMapManager::PROVIDER_SKYPACK])

        ->set('asset_mapper.importmap.resolver.jsdelivr', JspmResolver::class)
            ->args([service('http_client'), ImportMapManager::PROVIDER_JSDELIVR])
            ->tag('asset_mapper.importmap.resolver', ['resolver' => ImportMapManager::PROVIDER_JSDELIVR])

        ->set('asset_mapper.importmap.resolver.unpkg', JspmResolver::class)
            ->args([service('http_client'), ImportMapManager::PROVIDER_UNPKG])
            ->tag('asset_mapper.importmap.resolver', ['resolver' => ImportMapManager::PROVIDER_UNPKG])

        ->set('asset_mapper.importmap.renderer', ImportMapRenderer::class)
            ->args([
                service('asset_mapper.importmap.manager'),
                param('kernel.charset'),
                abstract_arg('polyfill URL'),
                abstract_arg('script HTML attributes'),
            ])

        ->set('asset_mapper.importmap.command.require', ImportMapRequireCommand::class)
            ->args([
                service('asset_mapper.importmap.manager'),
                service('asset_mapper'),
                param('kernel.project_dir'),
            ])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.remove', ImportMapRemoveCommand::class)
            ->args([service('asset_mapper.importmap.manager')])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.update', ImportMapUpdateCommand::class)
            ->args([service('asset_mapper.importmap.manager')])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.export', ImportMapExportCommand::class)
            ->args([service('asset_mapper.importmap.manager')])
            ->tag('console.command')
    ;
};
