<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Exception;

/**
 * Thrown when a type of given value does not match an expected type.
 *
 * @author Farhad Safarov <farhad.safarov@gmail.com>
 */
class InvalidTypeException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $expectedType,
        public readonly string $actualType,
        public readonly string $propertyPath,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Expected argument of type "%s", "%s" given at property path "%s".', $expectedType, 'NULL' === $actualType ? 'null' : $actualType, $propertyPath),
            previous: $previous,
        );
    }
}
