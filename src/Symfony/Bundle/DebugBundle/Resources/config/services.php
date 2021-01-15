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

use Monolog\Formatter\FormatterInterface;
use Symfony\Bridge\Monolog\Command\ServerLogCommand;
use Symfony\Bridge\Monolog\Formatter\ConsoleFormatter;
use Symfony\Bridge\Twig\Extension\DumpExtension;
use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;
use Symfony\Component\HttpKernel\EventListener\DumpListener;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Command\Descriptor\CliDescriptor;
use Symfony\Component\VarDumper\Command\Descriptor\HtmlDescriptor;
use Symfony\Component\VarDumper\Command\ServerDumpCommand;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\ContextProvider\CliContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextProvider\RequestContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextualizedDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Server\Connection;
use Symfony\Component\VarDumper\Server\DumpServer;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('env(VAR_DUMPER_SERVER)', '127.0.0.1:9912')
    ;

    $container->services()

        ->set('twig.extension.dump', DumpExtension::class)
            ->args([
                service('var_dumper.cloner'),
                service('var_dumper.html_dumper'),
            ])
            ->tag('twig.extension')

        ->set('data_collector.dump', DumpDataCollector::class)
            ->public()
            ->args([
                service('debug.stopwatch')->ignoreOnInvalid(),
                service('debug.file_link_formatter')->ignoreOnInvalid(),
                param('kernel.charset'),
                service('request_stack'),
                null, // var_dumper.cli_dumper or var_dumper.server_connection when debug.dump_destination is set
            ])
            ->tag('data_collector', [
                'id' => 'dump',
                'template' => '@Debug/Profiler/dump.html.twig',
                'priority' => 240,
            ])

        ->set('debug.dump_listener', DumpListener::class)
            ->args([
                service('var_dumper.cloner'),
                service('var_dumper.cli_dumper'),
                null,
            ])
            ->tag('kernel.event_subscriber')

        ->set('var_dumper.cloner', VarCloner::class)
            ->public()

        ->set('var_dumper.cli_dumper', CliDumper::class)
            ->args([
                null, // debug.dump_destination,
                param('kernel.charset'),
                0, // flags
            ])

        ->set('var_dumper.contextualized_cli_dumper', ContextualizedDumper::class)
            ->decorate('var_dumper.cli_dumper')
            ->args([
                service('var_dumper.contextualized_cli_dumper.inner'),
                [
                    'source' => inline_service(SourceContextProvider::class)->args([
                        param('kernel.charset'),
                        param('kernel.project_dir'),
                        service('debug.file_link_formatter')->nullOnInvalid(),
                    ]),
                ],
            ])

        ->set('var_dumper.html_dumper', HtmlDumper::class)
            ->args([
                null,
                param('kernel.charset'),
                0, // flags
            ])
            ->call('setDisplayOptions', [
                ['fileLinkFormat' => service('debug.file_link_formatter')->ignoreOnInvalid()],
            ])

        ->set('var_dumper.server_connection', Connection::class)
            ->args([
                '', // server host
                [
                    'source' => inline_service(SourceContextProvider::class)->args([
                        param('kernel.charset'),
                        param('kernel.project_dir'),
                        service('debug.file_link_formatter')->nullOnInvalid(),
                    ]),
                    'request' => inline_service(RequestContextProvider::class)->args([service('request_stack')]),
                    'cli' => inline_service(CliContextProvider::class),
                ],
            ])

        ->set('var_dumper.dump_server', DumpServer::class)
            ->args([
                '', // server host
                service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'debug'])

        ->set('var_dumper.command.server_dump', ServerDumpCommand::class)
            ->args([
                service('var_dumper.dump_server'),
                [
                    'cli' => inline_service(CliDescriptor::class)->args([service('var_dumper.contextualized_cli_dumper.inner')]),
                    'html' => inline_service(HtmlDescriptor::class)->args([service('var_dumper.html_dumper')]),
                ],
            ])
            ->tag('console.command')

        ->set('monolog.command.server_log', ServerLogCommand::class)
    ;

    if (class_exists(ConsoleFormatter::class) && interface_exists(FormatterInterface::class)) {
        $container->services()->get('monolog.command.server_log')->tag('console.command');
    }
};
