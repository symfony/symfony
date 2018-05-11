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

/**
 * Handlers can implement this interface to handle multiple messages.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.1
 */
interface MessageSubscriberInterface extends MessageHandlerInterface
{
    /**
     * Returns a list of messages to be handled.
     *
     * It returns a list of messages like in the following example:
     *
     *     return [MyMessage::class];
     *
     * It can also change the priority per classes.
     *
     *     return [
     *         [FirstMessage::class, 0],
     *         [SecondMessage::class, -10],
     *     ];
     *
     * It can also specify a method and/or a priority per message:
     *
     *     return [
     *         FirstMessage::class => 'firstMessageMethod',
     *         SecondMessage::class => ['secondMessageMethod', 20],
     *     ];
     *
     * The `__invoke` method of the handler will be called as usual with the message to handle.
     */
    public static function getHandledMessages(): iterable;
}
