<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Extractor;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ConstructorArgumentTypeExtractorAggregate implements ConstructorArgumentTypeExtractorInterface
{
    /**
     * @param iterable<int, ConstructorArgumentTypeExtractorInterface> $extractors
     */
    public function __construct(
        private readonly iterable $extractors = [],
    ) {
    }

    public function getTypesFromConstructor(string $class, string $property): ?array
    {
        $output = [];
        foreach ($this->extractors as $extractor) {
            $value = $extractor->getTypesFromConstructor($class, $property);
            if (null !== $value) {
                $output[] = $value;
            }
        }

        if ([] === $output) {
            return null;
        }

        return array_merge([], ...$output);
    }
}
