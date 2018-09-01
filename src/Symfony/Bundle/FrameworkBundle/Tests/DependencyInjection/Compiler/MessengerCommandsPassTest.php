<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\MessengerCommandsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Tests\Fixtures\DummyReceiver;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpReceiver;

class MessengerCommandsPassTest extends TestCase
{
    public function testItRegistersMultipleReceiversAndSetsTheReceiverNamesOnTheCommand()
    {
        $container = new ContainerBuilder();
        $container->register('my_bus_name', MessageBusInterface::class)->addTag('messenger.bus')->setArgument(0, array());
        $container->register('console.command.messenger_consume_messages', ConsumeMessagesCommand::class)->setArguments(array(
            null,
            new Reference('messenger.receiver_locator'),
            null,
            null,
            null,
        ));

        $container->register(AmqpReceiver::class, AmqpReceiver::class)->addTag('messenger.receiver', array('alias' => 'amqp'));
        $container->register(DummyReceiver::class, DummyReceiver::class)->addTag('messenger.receiver', array('alias' => 'dummy'));

        (new MessengerCommandsPass())->process($container);

        $this->assertSame(array('amqp', 'dummy'), $container->getDefinition('console.command.messenger_consume_messages')->getArgument(3));
        $this->assertSame(array('my_bus_name'), $container->getDefinition('console.command.messenger_consume_messages')->getArgument(4));
    }
}
