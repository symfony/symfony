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

use Symfony\Component\Asset\Context\RequestStackContext;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\RemoteJsonManifestVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('asset.request_context.base_path', null)
        ->set('asset.request_context.secure', null)
    ;

    $container->services()
        ->set('assets.packages', Packages::class)
            ->args([
                service('assets._default_package'),
                tagged_iterator('assets.package', 'package'),
            ])

        ->alias(Packages::class, 'assets.packages')

        ->set('assets.empty_package', Package::class)
            ->args([
                service('assets.empty_version_strategy'),
            ])

        ->alias('assets._default_package', 'assets.empty_package')

        ->set('assets.context', RequestStackContext::class)
            ->args([
                service('request_stack'),
                param('asset.request_context.base_path'),
                param('asset.request_context.secure'),
            ])

        ->set('assets.path_package', PathPackage::class)
            ->abstract()
            ->args([
                abstract_arg('base path'),
                abstract_arg('version strategy'),
                service('assets.context'),
            ])

        ->set('assets.url_package', UrlPackage::class)
            ->abstract()
            ->args([
                abstract_arg('base URLs'),
                abstract_arg('version strategy'),
                service('assets.context'),
            ])

        ->set('assets.static_version_strategy', StaticVersionStrategy::class)
            ->abstract()
            ->args([
                abstract_arg('version'),
                abstract_arg('format'),
            ])

        ->set('assets.empty_version_strategy', EmptyVersionStrategy::class)

        ->set('assets.json_manifest_version_strategy', JsonManifestVersionStrategy::class)
            ->abstract()
            ->args([
                abstract_arg('manifest path'),
                service('http_client')->nullOnInvalid(),
            ])

        ->set('assets.remote_json_manifest_version_strategy', RemoteJsonManifestVersionStrategy::class)
            ->abstract()
            ->deprecate('symfony/framework-bundle', '5.3', 'The "%service_id%" service is deprecated, use "assets.json_manifest_version_strategy" instead.')
            ->args([
                abstract_arg('manifest url'),
                service('http_client'),
            ])
    ;
};
