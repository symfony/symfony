<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Component\Messenger\EventListener\MessagesSentToTransportsListener;
use Symfony\Component\Messenger\Test\Constraint as MessengerConstraint;

/*
 * @author Marilena Ruffelaere <marilena.ruffelaere@gmail.com>
 */

trait MessengerAssertionsTrait
{
    /**
     * @param int         $count   the expected number of messages
     * @param string|null $busName The busName to consider. If null all the collected messages will be counted.
     */
    public static function assertMessagesByBusCount(int $count, string $busName = null, string $message = ''): void
    {
        self::assertThat(self::getDispatchedMessagesByBusName($busName), new MessengerConstraint\MessageCount($count, $busName), $message);
    }

    /**
     * @param int    $count     the expected number of messages of the given class
     * @param string $className the message object class name
     */
    public static function assertMessagesOfClassCount(int $count, string $className, string $message = ''): void
    {
        self::assertThat(self::getDispatchedMessagesByClassName($className), new MessengerConstraint\MessageCount($count, $className), $message);
    }

    public static function getDispatchedMessagesByBusName(?string $busName, bool $ordered = false): array
    {
        $container = static::getContainer();
        if ($container->has('messenger.sent_messages_to_transport_listener')) {
            /** @var MessagesSentToTransportsListener $listener */
            $listener = $container->get('messenger.sent_messages_to_transport_listener');

            return array_column($listener->getSentMessagesByBus($busName), 'message');
        }
        static::fail('A client must have Messenger enabled to make messages assertions .
         Did you forget to require symfony/messenger ?');
    }

    public static function getDispatchedMessagesByClassName(string $className): array
    {
        $container = static::getContainer();

        if ($container->has('messenger.sent_messages_to_transport_listener')) {
            /** @var MessagesSentToTransportsListener $listener */
            $listener = $container->get('messenger.sent_messages_to_transport_listener');

            return array_column($listener->getSentMessagesByClassName($className), 'message');
        }
        static::fail('A client must have Messenger enabled to make messages assertions.
        Did you forget to require symfony/messenger ? ');
    }
}
