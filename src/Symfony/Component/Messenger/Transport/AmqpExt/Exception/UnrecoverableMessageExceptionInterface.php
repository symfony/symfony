<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt\Exception;

/**
 * If something goes wrong while consuming and handling a message from the AMQP broker, if the exception that is thrown
 * by the bus while dispatching the message implements this interface, the message will be nack and not re-queued.
 *
 * Bus continue handling messages.
 *
 * @author Frederic Bouchery <frederic@bouchery.fr>
 *
 * @experimental in 4.3
 */
interface UnrecoverableMessageExceptionInterface extends \Throwable
{
}
