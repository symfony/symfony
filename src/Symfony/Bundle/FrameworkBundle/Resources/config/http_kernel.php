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

use Symfony\Component\HttpKernel\StaticSiteGeneration\FilesystemStaticPageDumper;
use Symfony\Component\HttpKernel\StaticSiteGeneration\StaticPageDumperInterface;
use Symfony\Component\HttpKernel\StaticSiteGeneration\StaticPagesGenerator;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('http_kernel.static_site.pages_generator', StaticPagesGenerator::class)
            ->args([
                service('http_kernel'),
                service('routing.static_site.pages_uri_provider'),
            ])

        ->set('http_kernel.static_site.page_dumper.filesystem', FilesystemStaticPageDumper::class)
            ->args([
                param('kernel.project_dir'),
            ])
        ->alias('http_kernel.static_site.page_dumper', 'http_kernel.static_site.page_dumper.filesystem')
        ->alias(StaticPageDumperInterface::class, 'http_kernel.static_site.page_dumper')
    ;
};
