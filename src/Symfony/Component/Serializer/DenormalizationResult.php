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

final class DenormalizationResult
{
    private $denormalizedValue;
    private $invariantViolations = [];

    private function __construct()
    {
    }

    public static function success($denormalizedValue): self
    {
        $result = new self();
        $result->denormalizedValue = $denormalizedValue;

        return $result;
    }

    /**
     * @param array<string, array<InvariantViolation>> $invariantViolations
     */
    public static function failure(array $invariantViolations, $partiallyDenormalizedValue = null): self
    {
        $result = new self();
        $result->invariantViolations = $invariantViolations;
        $result->denormalizedValue = $partiallyDenormalizedValue;

        return $result;
    }

    public function isSucessful(): bool
    {
        return [] === $this->invariantViolations;
    }

    public function getDenormalizedValue()
    {
        return $this->denormalizedValue;
    }

    public function getInvariantViolations(): array
    {
        return $this->invariantViolations;
    }

    /**
     * @return array<string, array<InvariantViolation>>
     */
    public function getInvariantViolationsNestedIn(string $parentPath): array
    {
        if ('' === $parentPath) {
            throw new \InvalidArgumentException('Parent path cannot be empty.');
        }

        $nestedViolations = [];

        foreach ($this->invariantViolations as $path => $violations) {
            $path = '' !== $path ? "{$parentPath}.{$path}" : $parentPath;

            $nestedViolations[$path] = $violations;
        }

        return $nestedViolations;
    }

    /**
     * @return array<string, array<string>>
     */
    public function getInvariantViolationMessages(): array
    {
        $messages = [];

        foreach ($this->invariantViolations as $path => $violations) {
            foreach ($violations as $violation) {
                $messages[$path][] = $violation->getMessage();
            }
        }

        return $messages;
    }
}
