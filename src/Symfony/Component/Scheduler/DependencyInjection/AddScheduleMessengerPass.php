<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @internal
 */
class AddScheduleMessengerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $receivers = [];
        foreach ($container->findTaggedServiceIds('messenger.receiver') as $tags) {
            $receivers[$tags[0]['alias']] = true;
        }

        foreach ($container->findTaggedServiceIds('scheduler.schedule_provider') as $tags) {
            $name = $tags[0]['name'];
            $transportName = 'scheduler_'.$name;

            // allows to override the default transport registration
            // in case one needs to configure it further (like choosing a different serializer)
            if (isset($receivers[$transportName])) {
                continue;
            }

            $transportDefinition = (new Definition(TransportInterface::class))
                ->setFactory([new Reference('messenger.transport_factory'), 'createTransport'])
                ->setArguments(['schedule://'.$name, ['transport_name' => $transportName], new Reference('messenger.default_serializer')])
                ->addTag('messenger.receiver', ['alias' => $transportName])
            ;
            $container->setDefinition('messenger.transport.'.$transportName, $transportDefinition);
        }
    }
}
