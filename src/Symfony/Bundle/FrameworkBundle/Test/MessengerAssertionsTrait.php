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
     * @param int         $count       The expected number of messages
     * @param string|null $busName     The busName to consider
     * @param string|null $messageFQCN The message object class name to consider
     */
    public static function assertMessagesCount(int $count, string $busName = null, string $messageFQCN = null, string $message = ''): void
    {
        self::assertThat(self::getDispatchedMessages($busName, $messageFQCN), new MessengerConstraint\MessageCount($count, $busName), $message);
    }

    public static function getDispatchedMessages(string $busName = null, string $messageFQCN = null): array
    {
        $container = static::getContainer();
        if ($container->has('messenger.sent_messages_to_transport_listener')) {
            /** @var MessagesSentToTransportsListener $listener */
            $listener = $container->get('messenger.sent_messages_to_transport_listener');

            return array_column($listener->getSentMessages($busName, $messageFQCN), 'message');
        }
        static::fail('A client must have Messenger enabled to make messages assertions .
         Did you forget to require symfony/messenger ?');
    }
}
