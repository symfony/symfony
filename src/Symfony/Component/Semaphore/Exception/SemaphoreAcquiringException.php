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

use Symfony\Component\Semaphore\Key;

/**
 * SemaphoreAcquiringException is thrown when an issue happens during the acquisition of a semaphore.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class SemaphoreAcquiringException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(Key $key, string $message)
    {
        parent::__construct(sprintf('The semaphore "%s" could not be acquired: %s.', $key, $message));
    }
}
