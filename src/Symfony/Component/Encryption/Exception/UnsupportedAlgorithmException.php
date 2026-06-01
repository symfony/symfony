<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Encryption\Exception;

/**
 * Thrown when a requested algorithm is unknown or unavailable in this runtime.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
class UnsupportedAlgorithmException extends InvalidArgumentException
{
}
