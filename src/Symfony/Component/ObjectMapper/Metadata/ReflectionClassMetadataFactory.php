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
 * Metadata is cached per source and target class: the decorated factory is expected to derive its mappings from classes, not from instances.
 *
 * @phpstan-import-type ClassMetadata from ClassMetadataFactoryInterface
 *
 * @internal
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
final class ReflectionClassMetadataFactory implements ClassMetadataFactoryInterface
{
    use ClassHierarchyTrait;

    /**
     * @var array<class-string, array<class-string, ClassMetadata>>
     */
    private array $metadata = [];

    public function __construct(
        private readonly ObjectMapperMetadataFactoryInterface $metadataFactory,
    ) {
    }

    public function create(object $source, object $target): array
    {
        return $this->metadata[$source::class][$target::class] ??= $this->doCreate($source, $target);
    }

    /**
     * @return ClassMetadata
     */
    private function doCreate(object $source, object $target): array
    {
        $targetRefl = new \ReflectionClass($target);
        $constructor = $targetRefl->getConstructor();
        $parameters = [];
        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            $name = $parameter->getName();
            $parameters[$name] = [
                'hasDefault' => $parameter->isDefaultValueAvailable(),
                'default' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                'readOnly' => $targetRefl->hasProperty($name) && $targetRefl->getProperty($name)->isReadOnly(),
            ];
        }

        $classMappings = array_values($this->metadataFactory->create($source));

        return [
            'readMetadataFromTarget' => !$classMappings && !$this->sourceCarriesPropertyMetadata($source),
            'classMappings' => $classMappings,
            'classMappingsFromTarget' => $classMappings ? [] : array_values($this->metadataFactory->create($target)),
            'hasConstructor' => null !== $constructor,
            'constructorParameters' => $parameters,
            'requiredConstructorParameters' => $constructor?->getNumberOfRequiredParameters() ?? 0,
        ];
    }

    private function sourceCarriesPropertyMetadata(object $source): bool
    {
        foreach ($this->getAllProperties(new \ReflectionClass($source)) as $property) {
            if ($this->metadataFactory->create($source, $property->getName())) {
                return true;
            }
        }

        return false;
    }
}
