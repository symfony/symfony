<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Semaphore\Exception;

/**
 * SemaphoreStorageException is thrown when an issue happens during the manipulation of a semaphore in a store.
 *
 * @author Santiago San Martin <sanmartindev@gmail.com>
 */
class SemaphoreStorageException extends \RuntimeException implements ExceptionInterface
{
}
