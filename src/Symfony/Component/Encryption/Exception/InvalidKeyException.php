<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Exception;

/**
 * Thrown when a key is the wrong length, type, or purpose.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
class InvalidKeyException extends InvalidArgumentException
{
}
