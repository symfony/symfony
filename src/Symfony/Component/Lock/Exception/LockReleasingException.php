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
 * LockReleasingException is thrown when an issue happens during the release of a lock.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class LockReleasingException extends \RuntimeException implements ExceptionInterface
{
}
