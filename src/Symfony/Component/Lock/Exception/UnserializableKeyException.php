<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Exception;

/**
 * UnserializableKeyException is thrown when the key contains state that can no
 * be serialized and the user try to serialize it.
 * ie. Connection with a database, flock, semaphore, ...
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class UnserializableKeyException extends \RuntimeException implements ExceptionInterface
{
}
