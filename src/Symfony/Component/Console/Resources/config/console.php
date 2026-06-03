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

use Symfony\Component\Console\ArgumentResolver\ArgumentResolver;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\BackedEnumValueResolver;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\BuiltinTypeValueResolver;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\DateTimeValueResolver;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\DefaultValueResolver;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\InputFileValueResolver;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\MapInputValueResolver;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\ServiceValueResolver;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\UidValueResolver;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\VariadicValueResolver;
use Symfony\Component\Console\EventListener\ErrorListener;
use Symfony\Component\Dotenv\Command\DebugCommand as DotenvDebugCommand;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('console.error_listener', ErrorListener::class)
            ->args([
                service('logger')->nullOnInvalid(),
            ])
            ->tag('kernel.event_subscriber')
            ->tag('monolog.logger', ['channel' => 'console'])

        ->set('console.command.dotenv_debug', DotenvDebugCommand::class)
            ->args([
                param('kernel.environment'),
                param('kernel.project_dir'),
            ])
            ->tag('console.command')

        ->set('console.argument_resolver', ArgumentResolver::class)
            ->public()
            ->args([
                abstract_arg('argument value resolvers'),
                abstract_arg('named argument value resolvers'),
            ])

        ->set('console.argument_resolver.backed_enum', BackedEnumValueResolver::class)
            ->tag('console.argument_value_resolver', ['priority' => 100, 'name' => BackedEnumValueResolver::class])

        ->set('console.argument_resolver.uid', UidValueResolver::class)
            ->tag('console.argument_value_resolver', ['priority' => 100, 'name' => UidValueResolver::class])

        ->set('console.argument_resolver.input_file', InputFileValueResolver::class)
            ->tag('console.argument_value_resolver', ['priority' => 100, 'name' => InputFileValueResolver::class])

        ->set('console.argument_resolver.builtin_type', BuiltinTypeValueResolver::class)
            ->tag('console.argument_value_resolver', ['priority' => 100, 'name' => BuiltinTypeValueResolver::class])

        ->set('console.argument_resolver.datetime', DateTimeValueResolver::class)
            ->args([
                service('clock')->nullOnInvalid(),
            ])
            ->tag('console.argument_value_resolver', ['priority' => 100, 'name' => DateTimeValueResolver::class])

        ->set('console.argument_resolver.map_input', MapInputValueResolver::class)
            ->args([
                service('console.argument_resolver.builtin_type'),
                service('console.argument_resolver.backed_enum'),
                service('console.argument_resolver.datetime'),
                service('validator')->nullOnInvalid(),
            ])
            ->tag('console.argument_value_resolver', ['priority' => 100, 'name' => MapInputValueResolver::class])

        ->set('console.argument_resolver.service', ServiceValueResolver::class)
            ->args([
                abstract_arg('service locator, set in RegisterCommandArgumentLocatorsPass'),
            ])
            ->tag('console.argument_value_resolver', ['priority' => -50, 'name' => ServiceValueResolver::class])

        ->set('console.argument_resolver.default', DefaultValueResolver::class)
            ->tag('console.argument_value_resolver', ['priority' => -100, 'name' => DefaultValueResolver::class])

        ->set('console.argument_resolver.variadic', VariadicValueResolver::class)
            ->tag('console.argument_value_resolver', ['priority' => -150, 'name' => VariadicValueResolver::class])
    ;
};
