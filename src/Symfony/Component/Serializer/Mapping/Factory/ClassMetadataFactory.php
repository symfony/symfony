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

use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;

/**
 * Returns a {@link ClassMetadata}.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ClassMetadataFactory implements ClassMetadataFactoryInterface
{
    use ClassResolverTrait;

    private $loader;

    /**
     * @var array
     */
    private $loadedClasses;

    public function __construct(LoaderInterface $loader)
    {
        $this->loader = $loader;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFor($value)
    {
        $class = $this->getClass($value);

        if (isset($this->loadedClasses[$class])) {
            return $this->loadedClasses[$class];
        }

        $classMetadata = new ClassMetadata($class);
        $this->loader->loadClassMetadata($classMetadata);

        $reflectionClass = $classMetadata->getReflectionClass();

        // Include metadata from the parent class
        if ($parent = $reflectionClass->getParentClass()) {
            $classMetadata->merge($this->getMetadataFor($parent->name));
        }

        // Include metadata from all implemented interfaces
        foreach ($reflectionClass->getInterfaces() as $interface) {
            $classMetadata->merge($this->getMetadataFor($interface->name));
        }

        return $this->loadedClasses[$class] = $classMetadata;
    }

    /**
     * {@inheritdoc}
     */
    public function hasMetadataFor($value)
    {
        return \is_object($value) || (\is_string($value) && (\class_exists($value) || \interface_exists($value, false)));
    }
}
