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
 * Thrown for X.509 certificate parsing, validation, or generation failures.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
class CertificateException extends \RuntimeException implements ExceptionInterface
{
}
