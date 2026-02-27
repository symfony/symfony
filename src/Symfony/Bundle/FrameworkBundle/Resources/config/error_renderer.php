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

use Symfony\Bundle\FrameworkBundle\ErrorHandler\ErrorRenderer\RuntimeModeErrorRendererSelector;
use Symfony\Component\ErrorHandler\ErrorRenderer\CliErrorRenderer;
use Symfony\Component\ErrorHandler\ErrorRenderer\ErrorRendererInterface;
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

        ->set('error_handler.error_renderer.cli', CliErrorRenderer::class)

        ->set('error_handler.error_renderer.default', ErrorRendererInterface::class)
            ->factory([RuntimeModeErrorRendererSelector::class, 'select'])
            ->args([
                param('kernel.runtime_mode.web'),
                service_closure('error_renderer.html'),
                service_closure('error_renderer.cli'),
            ])

        ->alias('error_renderer.html', 'error_handler.error_renderer.html')
        ->alias('error_renderer.cli', 'error_handler.error_renderer.cli')
        ->alias('error_renderer.default', 'error_handler.error_renderer.default')
        ->alias('error_renderer', 'error_renderer.default')
    ;
};
