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
 * Marker interface for exceptions to indicate that handling a message should have worked.
 *
 * If something goes wrong while handling a message that's received from a transport
 * and the message should be retried, a handler can throw such an exception.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface RecoverableExceptionInterface extends \Throwable
{
}
