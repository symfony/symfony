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

use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('error_handler.error_renderer.html', HtmlErrorRenderer::class)
            ->args([
                inline_service()
                    ->factory([HtmlErrorRenderer::class, 'isDebug'])
                    ->args([
                        service('request_stack'),
                        param('kernel.debug'),
                    ]),
                param('kernel.charset'),
                service('debug.file_link_formatter')->nullOnInvalid(),
                param('kernel.project_dir'),
                inline_service()
                    ->factory([HtmlErrorRenderer::class, 'getAndCleanOutputBuffer'])
                    ->args([service('request_stack')]),
                service('logger')->nullOnInvalid(),
            ])

        ->alias('error_renderer.html', 'error_handler.error_renderer.html')
        ->alias('error_renderer', 'error_renderer.html')
    ;
};
