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

use Symfony\Bundle\WebProfilerBundle\Controller\ExceptionPanelController;
use Symfony\Bundle\WebProfilerBundle\Controller\ProfilerController;
use Symfony\Bundle\WebProfilerBundle\Controller\RouterController;
use Symfony\Bundle\WebProfilerBundle\Csp\ContentSecurityPolicyHandler;
use Symfony\Bundle\WebProfilerBundle\Csp\NonceGenerator;
use Symfony\Bundle\WebProfilerBundle\Profiler\CodeExtension;
use Symfony\Bundle\WebProfilerBundle\Twig\WebProfilerExtension;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

return static function (ContainerConfigurator $container) {
    $container->services()

        ->set('web_profiler.controller.profiler', ProfilerController::class)
            ->public()
            ->args([
                service('router')->nullOnInvalid(),
                service('profiler')->nullOnInvalid(),
                service('twig'),
                param('data_collector.templates'),
                service('web_profiler.csp.handler'),
                param('kernel.project_dir'),
            ])

        ->set('web_profiler.controller.router', RouterController::class)
            ->public()
            ->args([
                service('profiler')->nullOnInvalid(),
                service('twig'),
                service('router')->nullOnInvalid(),
                null,
                tagged_iterator('routing.expression_language_provider'),
            ])

        ->set('web_profiler.controller.exception_panel', ExceptionPanelController::class)
            ->public()
            ->args([
                service('error_handler.error_renderer.html'),
                service('profiler')->nullOnInvalid(),
            ])

        ->set('web_profiler.csp.handler', ContentSecurityPolicyHandler::class)
            ->args([
                inline_service(NonceGenerator::class),
            ])

        ->set('twig.extension.webprofiler', WebProfilerExtension::class)
            ->args([
                inline_service(HtmlDumper::class)
                    ->args([null, param('kernel.charset'), HtmlDumper::DUMP_LIGHT_ARRAY])
                    ->call('setDisplayOptions', [['maxStringLength' => 4096, 'fileLinkFormat' => service('debug.file_link_formatter')]]),
            ])
            ->tag('twig.extension')

        ->set('debug.file_link_formatter', FileLinkFormatter::class)
            ->args([
                param('debug.file_link_format'),
                service('request_stack')->ignoreOnInvalid(),
                param('kernel.project_dir'),
                '/_profiler/open?file=%%f&line=%%l#line%%l',
            ])

        ->set('debug.file_link_formatter.url_format', 'string')
            ->factory([FileLinkFormatter::class, 'generateUrlFormat'])
            ->args([
                service('router'),
                '_profiler_open_file',
                '?file=%%f&line=%%l#line%%l',
            ])

        ->set('twig.extension.code', CodeExtension::class)
            ->args([service('debug.file_link_formatter'), param('kernel.project_dir'), param('kernel.charset')])
            ->tag('twig.extension')
    ;
};
