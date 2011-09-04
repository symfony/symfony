<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bridge\Doctrine\Annotations;

use Doctrine\Common\Annotations\Reader;

/**
 * Allows the reader to be used in-place of Doctrine's reader.
 *
 * This can be removed once the BC layer is in place.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class IndexedReader implements Reader
{
    private $delegate;

    public function __construct(Reader $reader)
    {
        $this->delegate = $reader;
    }

    public function getClassAnnotations(\ReflectionClass $class)
    {
        $annotations = array();
        foreach ($this->delegate->getClassAnnotations($class) as $annot) {
            $annotations[get_class($annot)] = $annot;
        }

        return $annotations;
    }

    public function getClassAnnotation(\ReflectionClass $class, $annotation)
    {
        return $this->delegate->getClassAnnotation($class, $annotation);
    }

    public function getMethodAnnotations(\ReflectionMethod $method)
    {
        $annotations = array();
        foreach ($this->delegate->getMethodAnnotations($method) as $annot) {
            $annotations[get_class($annot)] = $annot;
        }

        return $annotations;
    }

    public function getMethodAnnotation(\ReflectionMethod $method, $annotation)
    {
        return $this->delegate->getMethodAnnotation($method, $annotation);
    }

    public function getPropertyAnnotations(\ReflectionProperty $property)
    {
        $annotations = array();
        foreach ($this->delegate->getPropertyAnnotations($property) as $annot) {
            $annotations[get_class($annot)] = $annot;
        }

        return $annotations;
    }

    public function getPropertyAnnotation(\ReflectionProperty $property, $annotation)
    {
        return $this->delegate->getPropertyAnnotation($property, $annotation);
    }
}
