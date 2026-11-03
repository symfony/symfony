<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle;

use Symfony\Bridge\Monolog\Command\ServerLogCommand;
use Symfony\Bundle\DebugBundle\Command\ServerDumpPlaceholderCommand;
use Symfony\Bundle\DebugBundle\DependencyInjection\Compiler\DumpDataCollectorPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\VarDumper\Caster\ReflectionCaster;
use Symfony\Component\VarDumper\VarDumper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugBundle extends AbstractBundle
{
    public function boot(): void
    {
        if ($this->container->getParameter('kernel.debug')) {
            $container = $this->container;

            // This code is here to lazy load the dump stack. This default
            // configuration is overridden in CLI mode on 'console.command' event.
            // The dump data collector is used by default, so dump output is sent to
            // the WDT. In a CLI context, if dump is used too soon, the data collector
            // will buffer it, and release it at the end of the script.
            VarDumper::setHandler(static function ($var, ?string $label = null) use ($container) {
                $dumper = $container->get('data_collector.dump');
                $cloner = $container->get('var_dumper.cloner');
                $handler = static function ($var, ?string $label = null) use ($dumper, $cloner) {
                    $var = $cloner->cloneVar($var);
                    if (null !== $label) {
                        $var = $var->withContext(['label' => $label]);
                    }

                    $dumper->dump($var);
                };
                VarDumper::setHandler($handler);
                $handler($var, $label);
            });
        }
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DumpDataCollectorPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->docUrl('https://symfony.com/doc/{version:major}.{version:minor}/reference/configuration/debug.html', 'symfony/debug-bundle')
            ->children()
                ->integerNode('max_items')
                    ->info('Max number of displayed items past the first level, -1 means no limit.')
                    ->min(-1)
                    ->defaultValue(2500)
                ->end()
                ->integerNode('min_depth')
                    ->info('Minimum tree depth to clone all the items, 1 is default.')
                    ->min(0)
                    ->defaultValue(1)
                ->end()
                ->integerNode('max_string_length')
                    ->info('Max length of displayed strings, -1 means no limit.')
                    ->min(-1)
                    ->defaultValue(-1)
                ->end()
                ->scalarNode('dump_destination')
                    ->info('A stream URL where dumps should be written to.')
                    ->example('php://stderr, or tcp://%env(VAR_DUMPER_SERVER)% when using the "server:dump" command')
                    ->defaultNull()
                ->end()
                ->enumNode('theme')
                    ->info('Changes the color of the dump() output when rendered directly on the templating. "dark" (default) or "light".')
                    ->example('dark')
                    ->values(['dark', 'light'])
                    ->defaultValue('dark')
                ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import('Resources/config/services.php');

        $container->getDefinition('var_dumper.cloner')
            ->addMethodCall('setMaxItems', [$config['max_items']])
            ->addMethodCall('setMinDepth', [$config['min_depth']])
            ->addMethodCall('setMaxString', [$config['max_string_length']])
            ->addMethodCall('addCasters', [ReflectionCaster::UNSET_CLOSURE_FILE_INFO]);

        if ('dark' !== $config['theme']) {
            $container->getDefinition('var_dumper.html_dumper')
                ->addMethodCall('setTheme', [$config['theme']]);
        }

        if (null === $config['dump_destination']) {
            $container->getDefinition('var_dumper.command.server_dump')
                ->setClass(ServerDumpPlaceholderCommand::class)
            ;
        } elseif (str_starts_with($config['dump_destination'], 'tcp://')) {
            $container->getDefinition('debug.dump_listener')
                ->replaceArgument(2, new Reference('var_dumper.server_connection'))
            ;
            $container->getDefinition('data_collector.dump')
                ->replaceArgument(4, new Reference('var_dumper.server_connection'))
            ;
            $container->getDefinition('var_dumper.dump_server')
                ->replaceArgument(0, $config['dump_destination'])
            ;
            $container->getDefinition('var_dumper.server_connection')
                ->replaceArgument(0, $config['dump_destination'])
            ;
        } else {
            $container->getDefinition('var_dumper.cli_dumper')
                ->replaceArgument(0, $config['dump_destination'])
            ;
            $container->getDefinition('data_collector.dump')
                ->replaceArgument(4, new Reference('var_dumper.cli_dumper'))
            ;
            $container->getDefinition('var_dumper.command.server_dump')
                ->setClass(ServerDumpPlaceholderCommand::class)
            ;
        }

        $container->getDefinition('var_dumper.cli_dumper')
            ->addMethodCall('setDisplayOptions', [[
                'fileLinkFormat' => new Reference('debug.file_link_formatter', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE),
            ]])
        ;

        if (!class_exists(Command::class) || !class_exists(ServerLogCommand::class)) {
            $container->removeDefinition('monolog.command.server_log');
        }
    }

    /**
     * Symfony\Component\DependencyInjection\Kernel\AbstractBundle::getPath()
     * assumes the modern directory structure by default which is not the case
     * for DebugBundle.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
