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
 *
 * @implements DataTransformerInterface<mixed, array>
 */
class ValueToDuplicatesTransformer implements DataTransformerInterface
{
    public function __construct(
        private array $keys,
    ) {
    }

    public function transform(mixed $value): array
    {
        $result = [];

        foreach ($this->keys as $key) {
            $result[$key] = $value;
        }

        return $result;
    }

    public function reverseTransform(mixed $value): mixed
    {
        if (!\is_array($value)) {
            throw new TransformationFailedException('Expected an array.');
        }

        $result = current($value);
        $emptyKeys = [];

        foreach ($this->keys as $key) {
            if (isset($value[$key]) && false !== $value[$key] && [] !== $value[$key]) {
                if ($value[$key] !== $result) {
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

            throw new TransformationFailedException(\sprintf('The keys "%s" should not be empty.', implode('", "', $emptyKeys)));
        }

        return $result;
    }
}
