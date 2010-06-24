<?php

namespace Symfony\Components\Validator\Mapping;

use Symfony\Components\Validator\Mapping\Loader\LoaderInterface;

class ClassMetadataFactory implements ClassMetadataFactoryInterface
{
    protected $loader;

    protected $loadedClasses = array();

    public function __construct(LoaderInterface $loader)
    {
        $this->loader = $loader;
    }

    public function getClassMetadata($class)
    {
        $class = ltrim($class, '\\');

        if (!isset($this->loadedClasses[$class])) {
            $metadata = new ClassMetadata($class);

            // Include constraints from the parent class
            if ($parent = $metadata->getReflectionClass()->getParentClass()) {
                $metadata->mergeConstraints($this->getClassMetadata($parent->getName()));
            }

            // Include constraints from all implemented interfaces
            foreach ($metadata->getReflectionClass()->getInterfaces() as $interface) {
                $metadata->mergeConstraints($this->getClassMetadata($interface->getName()));
            }

            $this->loader->loadClassMetadata($metadata);

            $this->loadedClasses[$class] = $metadata;
        }

        return $this->loadedClasses[$class];
    }
}