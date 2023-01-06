<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

/**
 * AccessDeniedException is thrown when the account has not the required role.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AccessDeniedException extends RuntimeException
{
    private array $attributes = [];
    private mixed $subject = null;

    public function __construct(string $message = 'Access Denied.', \Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array|string $attributes)
    {
        $this->attributes = (array) $attributes;
    }

    public function getSubject(): mixed
    {
        return $this->subject;
    }

    public function setSubject(mixed $subject)
    {
        $this->subject = $subject;
    }
}
