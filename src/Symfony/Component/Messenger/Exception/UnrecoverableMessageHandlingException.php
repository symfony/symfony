<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

/**
 * Thrown while handling a message to indicate that handling will continue to fail.
 *
 * If something goes wrong while handling a message that's received from a transport
 * and the message should not be retried, a handler can throw this exception.
 *
 * @author Frederic Bouchery <frederic@bouchery.fr>
 *
 * @experimental in 4.3
 */
class UnrecoverableMessageHandlingException extends RuntimeException
{
}
