<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Exception;

use Symfony\Component\Serializer\InvariantViolation;

final class InvariantViolationException extends \RuntimeException implements ExceptionInterface
{
    /**
     * @var array<string, array<InvariantViolation>>
     */
    private $violations;

    /**
     * @param array<string, array<InvariantViolation>> $violations
     */
    public function __construct(array $violations)
    {
        parent::__construct('Denormalization failed because some values were invalid.');

        $this->violations = $violations;
    }

    /**
     * @return array<string, array<InvariantViolation>>
     */
    public function getViolations(): array
    {
        return $this->violations;
    }

    /**
     * @return array<string, array<InvariantViolation>>
     */
    public function getViolationsNestedIn(string $parentPath): array
    {
        if ('' === $parentPath) {
            throw new \InvalidArgumentException('Parent path cannot be empty.');
        }

        $nestedViolations = [];

        foreach ($this->violations as $path => $violations) {
            $path = '' !== $path ? "{$parentPath}.{$path}" : $parentPath;

            $nestedViolations[$path] = $violations;
        }

        return $nestedViolations;
    }

    /**
     * @return array<string, array<string>>
     */
    public function getViolationMessages(): array
    {
        $messages = [];

        foreach ($this->violations as $path => $violations) {
            foreach ($violations as $violation) {
                $messages[$path][] = $violation->getMessage();
            }
        }

        return $messages;
    }
}
