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
 * Thrown when decryption or authenticated-message opening fails.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
class DecryptionException extends EncryptionException
{
}
