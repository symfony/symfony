<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Result;

use Throwable;

final class NormalizationResult
{
    private $normalizedValue;
    private $exceptions = [];

    private function __construct()
    {
    }

    public static function success($normalizedValue): self
    {
        $result = new self();
        $result->normalizedValue = $normalizedValue;

        return $result;
    }

    /**
     * @param array<string, Throwable> $exceptions
     */
    public static function failure(array $exceptions, $partiallyNormalizedValue = null): self
    {
        $result = new self();
        $result->exceptions = $exceptions;
        $result->normalizedValue = $partiallyNormalizedValue;

        return $result;
    }

    public function isSucessful(): bool
    {
        return [] === $this->exceptions;
    }

    public function getNormalizedValue()
    {
        return $this->normalizedValue;
    }

    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    /**
     * @return array<string, Throwable>
     */
    public function getExceptionsNestedIn(string $parentPath): array
    {
        if ('' === $parentPath) {
            throw new \InvalidArgumentException('Parent path cannot be empty.');
        }

        $nestedExceptions = [];

        foreach ($this->exceptions as $path => $exception) {
            $path = '' !== $path ? "{$parentPath}.{$path}" : $parentPath;

            $nestedExceptions[$path] = $exception;
        }

        return $nestedExceptions;
    }

    /**
     * @return array<string, string>
     */
    public function getExceptionMessages(): array
    {
        $messages = [];

        foreach ($this->exceptions as $path => $exception) {
            $messages[$path] = $exception->getMessage();
        }

        return $messages;
    }
}
