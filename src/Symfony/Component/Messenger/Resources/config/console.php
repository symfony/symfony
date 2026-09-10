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

use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Command\DebugCommand;
use Symfony\Component\Messenger\Command\FailedMessagesRemoveCommand;
use Symfony\Component\Messenger\Command\FailedMessagesRetryCommand;
use Symfony\Component\Messenger\Command\FailedMessagesShowCommand;
use Symfony\Component\Messenger\Command\SetupTransportsCommand;
use Symfony\Component\Messenger\Command\ShowMessagesCommand;
use Symfony\Component\Messenger\Command\StatsCommand;
use Symfony\Component\Messenger\Command\StopWorkersCommand;

return static function (ContainerConfigurator $container) {
    $container->services()
        ->set('console.command.messenger_consume_messages', ConsumeMessagesCommand::class)
            ->args([
                abstract_arg('Routable message bus'),
                service('messenger.receiver_locator'),
                service('event_dispatcher'),
                service('logger')->nullOnInvalid(),
                [], // Receiver names
                service('messenger.listener.reset_services')->nullOnInvalid(),
                [], // Bus names
                service('messenger.rate_limiter_locator')->nullOnInvalid(),
                null,
                param('kernel.project_dir').'/bin/console',
            ])
            ->tag('console.command')
            ->tag('monolog.logger', ['channel' => 'messenger'])

        ->set('console.command.messenger_setup_transports', SetupTransportsCommand::class)
            ->args([
                service('messenger.receiver_locator'),
                [], // Receiver names
            ])
            ->tag('console.command')

        ->set('console.command.messenger_debug', DebugCommand::class)
            ->args([
                [], // Message to handlers mapping
            ])
            ->tag('console.command')

        ->set('console.command.messenger_stop_workers', StopWorkersCommand::class)
            ->args([
                service('cache.messenger.restart_workers_signal'),
            ])
            ->tag('console.command')
            ->tag('container.remove_if_missing', ['service' => 'cache.messenger.restart_workers_signal'])

        ->set('console.command.messenger_failed_messages_retry', FailedMessagesRetryCommand::class)
            ->args([
                abstract_arg('Default failure receiver name'),
                abstract_arg('Receivers'),
                service('messenger.routable_message_bus'),
                service('event_dispatcher'),
                service('logger')->nullOnInvalid(),
                service('.messenger.transport.native_php_serializer')->nullOnInvalid(),
                null,
            ])
            ->tag('console.command')
            ->tag('monolog.logger', ['channel' => 'messenger'])

        ->set('console.command.messenger_failed_messages_show', FailedMessagesShowCommand::class)
            ->args([
                abstract_arg('Default failure receiver name'),
                abstract_arg('Receivers'),
                service('.messenger.transport.native_php_serializer')->nullOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.command.messenger_failed_messages_remove', FailedMessagesRemoveCommand::class)
            ->args([
                abstract_arg('Default failure receiver name'),
                abstract_arg('Receivers'),
                service('.messenger.transport.native_php_serializer')->nullOnInvalid(),
            ])
            ->tag('console.command')

        ->set('console.command.messenger_stats', StatsCommand::class)
            ->args([
                service('messenger.receiver_locator'),
                abstract_arg('Receivers names'),
            ])
            ->tag('console.command')

        ->set('console.command.messenger_show', ShowMessagesCommand::class)
            ->args([
                service('messenger.receiver_locator'),
                abstract_arg('Receivers names'),
                service('.messenger.transport.native_php_serializer')->nullOnInvalid(),
            ])
            ->tag('console.command')
    ;
};
