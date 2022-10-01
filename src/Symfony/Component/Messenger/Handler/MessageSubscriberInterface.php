<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handlers can implement this interface to handle multiple messages.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @deprecated since Symfony 6.2, use the {@see AsMessageHandler} attribute instead
 */
interface MessageSubscriberInterface extends MessageHandlerInterface
{
    /**
     * Returns a list of messages to be handled.
     *
     * It returns a list of messages like in the following example:
     *
     *     yield MyMessage::class;
     *
     * It can also change the priority per classes.
     *
     *     yield FirstMessage::class => ['priority' => 0];
     *     yield SecondMessage::class => ['priority' => -10];
     *
     * It can also specify a method, a priority, a bus and/or a transport per message:
     *
     *     yield FirstMessage::class => ['method' => 'firstMessageMethod'];
     *     yield SecondMessage::class => [
     *         'method' => 'secondMessageMethod',
     *         'priority' => 20,
     *         'bus' => 'my_bus_name',
     *         'from_transport' => 'your_transport_name',
     *     ];
     *
     * The benefit of using `yield` instead of returning an array is that you can `yield` multiple times the
     * same key and therefore subscribe to the same message multiple times with different options.
     *
     * The `__invoke` method of the handler will be called as usual with the message to handle.
     */
    public static function getHandledMessages(): iterable;
}
