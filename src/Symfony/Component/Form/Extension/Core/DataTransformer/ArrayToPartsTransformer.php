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
 * @implements DataTransformerInterface<array, array>
 */
class ArrayToPartsTransformer implements DataTransformerInterface
{
    public function __construct(
        private array $partMapping,
    ) {
    }

    public function transform(mixed $value): mixed
    {
        if (!\is_array($value ??= [])) {
            throw new TransformationFailedException('Expected an array.');
        }

        $result = [];

        foreach ($this->partMapping as $partKey => $originalKeys) {
            if (!$value) {
                $result[$partKey] = null;
            } else {
                $result[$partKey] = array_intersect_key($value, array_flip($originalKeys));
            }
        }

        return $result;
    }

    public function reverseTransform(mixed $value): mixed
    {
        if (!\is_array($value)) {
            throw new TransformationFailedException('Expected an array.');
        }

        $result = [];
        $emptyKeys = [];

        foreach ($this->partMapping as $partKey => $originalKeys) {
            if (!empty($value[$partKey])) {
                foreach ($originalKeys as $originalKey) {
                    if (isset($value[$partKey][$originalKey])) {
                        $result[$originalKey] = $value[$partKey][$originalKey];
                    }
                }
            } else {
                $emptyKeys[] = $partKey;
            }
        }

        if (\count($emptyKeys) > 0) {
            if (\count($emptyKeys) === \count($this->partMapping)) {
                // All parts empty
                return null;
            }

            throw new TransformationFailedException(\sprintf('The keys "%s" should not be empty.', implode('", "', $emptyKeys)));
        }

        return $result;
    }
}
