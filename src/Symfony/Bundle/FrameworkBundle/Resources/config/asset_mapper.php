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
use Symfony\Component\AssetMapper\Command\ImportMapAuditCommand;
use Symfony\Component\AssetMapper\Command\ImportMapInstallCommand;
use Symfony\Component\AssetMapper\Command\ImportMapOutdatedCommand;
use Symfony\Component\AssetMapper\Command\ImportMapRemoveCommand;
use Symfony\Component\AssetMapper\Command\ImportMapRequireCommand;
use Symfony\Component\AssetMapper\Command\ImportMapUpdateCommand;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\AssetMapper\Compiler\CssAssetUrlCompiler;
use Symfony\Component\AssetMapper\Compiler\JavaScriptImportPathCompiler;
use Symfony\Component\AssetMapper\Compiler\SourceMappingUrlsCompiler;
use Symfony\Component\AssetMapper\Factory\CachedMappedAssetFactory;
use Symfony\Component\AssetMapper\Factory\MappedAssetFactory;
use Symfony\Component\AssetMapper\ImportMap\ImportMapAuditor;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapGenerator;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;
use Symfony\Component\AssetMapper\ImportMap\ImportMapUpdateChecker;
use Symfony\Component\AssetMapper\ImportMap\ImportMapVersionChecker;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageDownloader;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageStorage;
use Symfony\Component\AssetMapper\ImportMap\Resolver\JsDelivrEsmResolver;
use Symfony\Component\AssetMapper\MapperAwareAssetPackage;
use Symfony\Component\AssetMapper\Path\LocalPublicAssetsFilesystem;
use Symfony\Component\AssetMapper\Path\PublicAssetsPathResolver;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('asset_mapper', AssetMapper::class)
            ->args([
                service('asset_mapper.repository'),
                service('asset_mapper.mapped_asset_factory'),
                service('asset_mapper.compiled_asset_mapper_config_reader'),
            ])
        ->alias(AssetMapperInterface::class, 'asset_mapper')

        ->set('asset_mapper.mapped_asset_factory', MappedAssetFactory::class)
            ->args([
                service('asset_mapper.public_assets_path_resolver'),
                service('asset_mapper_compiler'),
                abstract_arg('vendor directory'),
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
                abstract_arg('exclude dot files'),
            ])

        ->set('asset_mapper.public_assets_path_resolver', PublicAssetsPathResolver::class)
            ->args([
                abstract_arg('asset public prefix'),
            ])

        ->set('asset_mapper.local_public_assets_filesystem', LocalPublicAssetsFilesystem::class)
            ->args([
                abstract_arg('public directory'),
            ])

        ->set('asset_mapper.compiled_asset_mapper_config_reader', CompiledAssetMapperConfigReader::class)
            ->args([
                abstract_arg('public assets directory'),
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
                service('profiler')->nullOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')

        ->set('asset_mapper.command.compile', AssetMapperCompileCommand::class)
            ->args([
                service('asset_mapper.compiled_asset_mapper_config_reader'),
                service('asset_mapper'),
                service('asset_mapper.importmap.generator'),
                service('asset_mapper.local_public_assets_filesystem'),
                param('kernel.project_dir'),
                param('kernel.debug'),
                service('event_dispatcher')->nullOnInvalid(),
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
                service('asset_mapper.importmap.config_reader'),
                abstract_arg('missing import mode'),
                service('logger'),
            ])
            ->tag('asset_mapper.compiler')
            ->tag('monolog.logger', ['channel' => 'asset_mapper'])

        ->set('asset_mapper.importmap.config_reader', ImportMapConfigReader::class)
            ->args([
                abstract_arg('importmap.php path'),
                service('asset_mapper.importmap.remote_package_storage'),
            ])

        ->set('asset_mapper.importmap.manager', ImportMapManager::class)
            ->args([
                service('asset_mapper'),
                service('asset_mapper.importmap.config_reader'),
                service('asset_mapper.importmap.remote_package_downloader'),
                service('asset_mapper.importmap.resolver'),
            ])
        ->alias(ImportMapManager::class, 'asset_mapper.importmap.manager')

        ->set('asset_mapper.importmap.generator', ImportMapGenerator::class)
            ->args([
                service('asset_mapper'),
                service('asset_mapper.compiled_asset_mapper_config_reader'),
                service('asset_mapper.importmap.config_reader'),
            ])

        ->set('asset_mapper.importmap.remote_package_storage', RemotePackageStorage::class)
            ->args([
                abstract_arg('vendor directory'),
            ])

        ->set('asset_mapper.importmap.remote_package_downloader', RemotePackageDownloader::class)
            ->args([
                service('asset_mapper.importmap.remote_package_storage'),
                service('asset_mapper.importmap.config_reader'),
                service('asset_mapper.importmap.resolver'),
            ])

        ->set('asset_mapper.importmap.version_checker', ImportMapVersionChecker::class)
            ->args([
                service('asset_mapper.importmap.config_reader'),
                service('asset_mapper.importmap.remote_package_downloader'),
            ])

        ->set('asset_mapper.importmap.resolver', JsDelivrEsmResolver::class)
            ->args([service('http_client')])

        ->set('asset_mapper.importmap.renderer', ImportMapRenderer::class)
            ->args([
                service('asset_mapper.importmap.generator'),
                service('assets.packages')->nullOnInvalid(),
                param('kernel.charset'),
                abstract_arg('polyfill URL'),
                abstract_arg('script HTML attributes'),
                service('request_stack'),
            ])

        ->set('asset_mapper.importmap.auditor', ImportMapAuditor::class)
        ->args([
            service('asset_mapper.importmap.config_reader'),
            service('http_client'),
        ])
        ->set('asset_mapper.importmap.update_checker', ImportMapUpdateChecker::class)
        ->args([
            service('asset_mapper.importmap.config_reader'),
            service('http_client'),
        ])

        ->set('asset_mapper.importmap.command.require', ImportMapRequireCommand::class)
            ->args([
                service('asset_mapper.importmap.manager'),
                service('asset_mapper.importmap.version_checker'),
            ])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.remove', ImportMapRemoveCommand::class)
            ->args([service('asset_mapper.importmap.manager')])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.update', ImportMapUpdateCommand::class)
            ->args([
                service('asset_mapper.importmap.manager'),
                service('asset_mapper.importmap.version_checker'),
            ])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.install', ImportMapInstallCommand::class)
            ->args([
                service('asset_mapper.importmap.remote_package_downloader'),
                param('kernel.project_dir'),
            ])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.audit', ImportMapAuditCommand::class)
            ->args([service('asset_mapper.importmap.auditor')])
            ->tag('console.command')

        ->set('asset_mapper.importmap.command.outdated', ImportMapOutdatedCommand::class)
            ->args([service('asset_mapper.importmap.update_checker')])
            ->tag('console.command')
    ;
};
