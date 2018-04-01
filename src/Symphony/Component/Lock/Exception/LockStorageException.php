<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Lock\Exception;

/**
 * LockStorageException is thrown when an issue happens during the manipulation of a lock in a store.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class LockStorageException extends \RuntimeException implements ExceptionInterface
{
}
