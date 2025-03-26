<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping\Factory;

use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;

trigger_deprecation('symfony/serializer', '7.3', 'The "%s" class is deprecated.', CompiledClassMetadataFactory::class);

/**
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 *
 * @deprecated since Symfony 7.3
 */
final class CompiledClassMetadataFactory implements ClassMetadataFactoryInterface
{
    private array $compiledClassMetadata = [];

    private array $loadedClasses = [];

    public function __construct(
        string $compiledClassMetadataFile,
        private readonly ClassMetadataFactoryInterface $classMetadataFactory,
    ) {
        if (!file_exists($compiledClassMetadataFile)) {
            throw new \RuntimeException("File \"{$compiledClassMetadataFile}\" could not be found.");
        }

        $compiledClassMetadata = require $compiledClassMetadataFile;
        if (!\is_array($compiledClassMetadata)) {
            throw new \RuntimeException(\sprintf('Compiled metadata must be of the type array, %s given.', \gettype($compiledClassMetadata)));
        }

        $this->compiledClassMetadata = $compiledClassMetadata;
    }

    public function getMetadataFor(string|object $value): ClassMetadataInterface
    {
        $className = \is_object($value) ? $value::class : $value;

        if (!isset($this->compiledClassMetadata[$className])) {
            return $this->classMetadataFactory->getMetadataFor($value);
        }

        if (!isset($this->loadedClasses[$className])) {
            $classMetadata = new ClassMetadata($className);
            foreach ($this->compiledClassMetadata[$className][0] as $name => $compiledAttributesMetadata) {
                $classMetadata->attributesMetadata[$name] = $attributeMetadata = new AttributeMetadata($name);
                [$attributeMetadata->groups, $attributeMetadata->maxDepth, $attributeMetadata->serializedName] = $compiledAttributesMetadata;
            }
            $classMetadata->classDiscriminatorMapping = $this->compiledClassMetadata[$className][1]
                ? new ClassDiscriminatorMapping(...$this->compiledClassMetadata[$className][1])
                : null
            ;

            $this->loadedClasses[$className] = $classMetadata;
        }

        return $this->loadedClasses[$className];
    }

    public function hasMetadataFor(mixed $value): bool
    {
        $className = \is_object($value) ? $value::class : $value;

        return isset($this->compiledClassMetadata[$className]) || $this->classMetadataFactory->hasMetadataFor($value);
    }
}
