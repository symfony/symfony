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
 * LockExpiredException is thrown when a lock may conflict due to a TTL expiration.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class LockExpiredException extends \RuntimeException implements ExceptionInterface
{
}
