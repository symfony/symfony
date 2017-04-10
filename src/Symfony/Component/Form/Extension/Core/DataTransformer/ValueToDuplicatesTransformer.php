<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValueToDuplicatesTransformer implements DataTransformerInterface
{
    private $keys;
    private $comparator;

    /**
     * Constructor.
     *
     * @param array    $keys       The compared keys
     * @param callable $comparator The comparator callable to compare values
     */
    public function __construct(array $keys, callable $comparator = null)
    {
        $this->keys = $keys;
        $this->comparator = $comparator;
    }

    /**
     * Duplicates the given value through the array.
     *
     * @param mixed $value The value
     *
     * @return array The array
     */
    public function transform($value)
    {
        $result = array();

        foreach ($this->keys as $key) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Extracts the duplicated value from an array.
     *
     * @param array $array
     *
     * @return mixed The value
     *
     * @throws TransformationFailedException If the given value is not an array or
     *                                       if the given array can not be transformed.
     */
    public function reverseTransform($array)
    {
        if (!is_array($array)) {
            throw new TransformationFailedException('Expected an array.');
        }

        $result = current($array);
        $emptyKeys = array();
        $comparator = is_callable($this->comparator) ? $this->comparator : function ($value1, $value2) {
            return $value1 === $value2;
        };

        foreach ($this->keys as $key) {
            if (isset($array[$key]) && '' !== $array[$key] && false !== $array[$key] && array() !== $array[$key]) {
                if (!$comparator($result, $array[$key], $key)) {
                    throw new TransformationFailedException(
                        'All values in the array should be the same'
                    );
                }
            } else {
                $emptyKeys[] = $key;
            }
        }

        if (count($emptyKeys) > 0) {
            if (count($emptyKeys) == count($this->keys)) {
                // All keys empty
                return;
            }

            throw new TransformationFailedException(
                 sprintf('The keys "%s" should not be empty', implode('", "', $emptyKeys)
            ));
        }

        return $result;
    }
}
