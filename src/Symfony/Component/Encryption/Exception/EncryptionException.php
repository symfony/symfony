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
 * Base class for runtime crypto failures (encryption, signing, engine errors).
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
class EncryptionException extends \RuntimeException implements ExceptionInterface
{
}
