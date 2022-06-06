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
 * SemaphoreExpiredException is thrown when a semaphore may conflict due to a TTL expiration.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class SemaphoreExpiredException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(Key $key, string $message)
    {
        parent::__construct(sprintf('The semaphore "%s" has expired: %s.', $key, $message));
    }
}
