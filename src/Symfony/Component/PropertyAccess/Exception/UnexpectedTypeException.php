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
     * @param mixed                 $value     The unexpected value found while traversing property path
     * @param PropertyPathInterface $path      The property path
     * @param int                   $pathIndex The property path index when the unexpected value was found
     */
    public function __construct($value, $path, $pathIndex = null)
    {
        if (3 === \func_num_args() && $path instanceof PropertyPathInterface) {
            $message = sprintf(
                'PropertyAccessor requires a graph of objects or arrays to operate on, '.
                'but it found type "%s" while trying to traverse path "%s" at property "%s".',
                \gettype($value),
                (string) $path,
                $path->getElement($pathIndex)
            );
        } else {
            @trigger_error('The '.__CLASS__.' constructor now expects 3 arguments: the invalid property value, the '.__NAMESPACE__.'\PropertyPathInterface object and the current index of the property path.', E_USER_DEPRECATED);

            $message = sprintf(
                'Expected argument of type "%s", "%s" given',
                $path,
                \is_object($value) ? \get_class($value) : \gettype($value)
            );
        }

        parent::__construct($message);
    }
}
