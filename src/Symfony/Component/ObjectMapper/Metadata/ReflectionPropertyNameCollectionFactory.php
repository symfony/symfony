<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Metadata;

use Symfony\Component\ObjectMapper\ClassHierarchyTrait;

/**
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class ReflectionPropertyNameCollectionFactory implements PropertyNameCollectionFactoryInterface
{
    use ClassHierarchyTrait;

    /**
     * @var array<class-string, list<string>>
     */
    private array $collections = [];

    public function create(object $object): array
    {
        return $this->collections[$object::class] ??= array_values(array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            array_filter($this->getAllProperties(new \ReflectionClass($object)), static fn (\ReflectionProperty $property): bool => !$property->isStatic()),
        ));
    }
}
