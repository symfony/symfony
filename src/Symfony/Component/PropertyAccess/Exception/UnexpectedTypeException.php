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

use Symfony\Component\PropertyAccess\PropertyPathInterface;

/**
 * Thrown when a value does not match an expected type.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UnexpectedTypeException extends RuntimeException
{
    /**
     * @param mixed $value     The unexpected value found while traversing property path
     * @param int   $pathIndex The property path index when the unexpected value was found
     */
    public function __construct(mixed $value, PropertyPathInterface $path, int $pathIndex)
    {
        $message = sprintf(
            'PropertyAccessor requires a graph of objects or arrays to operate on, '.
            'but it found type "%s" while trying to traverse path "%s" at property "%s".',
            \gettype($value),
            (string) $path,
            $path->getElement($pathIndex)
        );

        parent::__construct($message);
    }
}
