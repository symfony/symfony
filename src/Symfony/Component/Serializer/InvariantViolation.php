<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer;

final class InvariantViolation
{
    private $normalizedValue;
    private $message;
    private $throwable;

    public function __construct($normalizedValue, string $message, ?\Throwable $throwable = null)
    {
        $this->normalizedValue = $normalizedValue;
        $this->message = $message;
        $this->throwable = $throwable;
    }

    public function getNormalizedValue()
    {
        return $this->normalizedValue;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getThrowable(): ?\Throwable
    {
        return $this->throwable;
    }
}
