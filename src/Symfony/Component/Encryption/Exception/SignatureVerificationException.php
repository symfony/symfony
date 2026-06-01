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
 * Thrown when a signature fails verification on an opening operation.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
class SignatureVerificationException extends EncryptionException
{
}
