<?php

namespace Symfony\Components\Validator\Mapping\Loader;

use Symfony\Components\Validator\Exception\MappingException;
use Symfony\Components\Validator\Mapping\ClassMetadata;
use Doctrine\Common\Annotations\AnnotationReader;

class AnnotationLoader implements LoaderInterface
{
    protected $reader;

    public function __construct()
    {
        $this->reader = new AnnotationReader();
        $this->reader->setDefaultAnnotationNamespace('Symfony\Components\Validator\Constraints\\');
        $this->reader->setAutoloadAnnotations(true);
    }

    /**
     * {@inheritDoc}
     */
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        $annotClass = 'Symfony\Components\Validator\Constraints\Validation';
        $reflClass = $metadata->getReflectionClass();
        $loaded = false;

        if ($annot = $this->reader->getClassAnnotation($reflClass, $annotClass)) {
            foreach ($annot->constraints as $constraint) {
                $metadata->addConstraint($constraint);
            }

            $loaded = true;
        }

        foreach ($reflClass->getProperties() as $property) {
            if ($annot = $this->reader->getPropertyAnnotation($property, $annotClass)) {
                foreach ($annot->constraints as $constraint) {
                    $metadata->addPropertyConstraint($property->getName(), $constraint);
                }

                $loaded = true;
            }
        }

        foreach ($reflClass->getMethods() as $method) {
            if ($annot = $this->reader->getMethodAnnotation($method, $annotClass)) {
                foreach ($annot->constraints as $constraint) {
                    // TODO: clean this up
                    $name = lcfirst(substr($method->getName(), 0, 3)=='get' ? substr($method->getName(), 3) : substr($method->getName(), 2));

                    $metadata->addGetterConstraint($name, $constraint);
                }

                $loaded = true;
            }
        }

        return $loaded;
    }
}