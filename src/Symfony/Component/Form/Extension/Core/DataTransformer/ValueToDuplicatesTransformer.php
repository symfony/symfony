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

    public function __construct(array $keys)
    {
        $this->keys = $keys;
    }

    /**
     * Duplicates the given value through the array.
     *
     * @param mixed $value The value
     *
     * @return array
     */
    public function transform($value)
    {
        $result = [];

        foreach ($this->keys as $key) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Extracts the duplicated value from an array.
     *
     * @return mixed
     *
     * @throws TransformationFailedException if the given value is not an array or
     *                                       if the given array cannot be transformed
     */
    public function reverseTransform($array)
    {
        if (!\is_array($array)) {
            throw new TransformationFailedException('Expected an array.');
        }

        $result = current($array);
        $emptyKeys = [];

        foreach ($this->keys as $key) {
            if (isset($array[$key]) && false !== $array[$key] && [] !== $array[$key]) {
                if ($array[$key] !== $result) {
                    throw new TransformationFailedException('All values in the array should be the same.');
                }
            } else {
                $emptyKeys[] = $key;
            }
        }

        if (\count($emptyKeys) > 0) {
            if (\count($emptyKeys) == \count($this->keys)) {
                // All keys empty
                return null;
            }

            throw new TransformationFailedException(sprintf('The keys "%s" should not be empty.', implode('", "', $emptyKeys)));
        }

        return $result;
    }
}
