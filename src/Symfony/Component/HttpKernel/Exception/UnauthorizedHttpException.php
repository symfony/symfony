<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Exception;

/**
 * @author Ben Ramsey <ben@benramsey.com>
 */
class UnauthorizedHttpException extends HttpException
{
    /**
     * @param string          $challenge WWW-Authenticate challenge string
     * @param string|null     $message   The internal exception message
     * @param \Throwable|null $previous  The previous exception
     * @param int|null        $code      The internal exception code
     */
    public function __construct(string $challenge, ?string $message = '', \Throwable $previous = null, ?int $code = 0, array $headers = [])
    {
        $headers['WWW-Authenticate'] = $challenge;

        parent::__construct(401, $message, $previous, $headers, $code);
    }
}
