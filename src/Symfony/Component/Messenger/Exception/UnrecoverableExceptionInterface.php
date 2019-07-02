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
 * Marker interface for exceptions to indicate that handling a message will continue to fail.
 *
 * If something goes wrong while handling a message that's received from a transport
 * and the message should not be retried, a handler can throw such an exception.
 *
 * @author Tobias Schultze <http://tobion.de>
 */
interface UnrecoverableExceptionInterface extends \Throwable
{
}
