<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PasswordHasher\Exception;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class InvalidPasswordException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(string $message = 'Invalid password.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
