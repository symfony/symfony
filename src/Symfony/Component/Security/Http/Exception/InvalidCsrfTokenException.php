<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when the CSRF token checked by the #[IsCsrfTokenValid] attribute is invalid.
 *
 * Unlike its Security\Core counterpart, which reports an authentication failure, this one
 * reports a forbidden request: the controller is reached by an already identified caller.
 *
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
class InvalidCsrfTokenException extends HttpException
{
    public function __construct(string $message = 'Invalid CSRF token.', ?\Throwable $previous = null, int $code = 0, array $headers = [])
    {
        parent::__construct(403, $message, $previous, $headers, $code);
    }
}
