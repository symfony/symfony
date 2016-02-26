<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\ControllerMetadata;

use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Adapter\AnnotationAdapterInterface;

/**
 * Responsible for storing metadata of a controller.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class ClassMetadata implements \Serializable
{
    /**
     * @var string
     */
    private $className;

    /**
     * @var AnnotationAdapterInterface[]
     */
    private $annotations;

    /**
     * @var array
     */
    private $methods;

    /**
     * @param string                       $className
     * @param MethodMetadata[]             $methods
     * @param AnnotationAdapterInterface[] $annotations
     */
    public function __construct($className, array $methods = [], array $annotations = [])
    {
        $this->className = $className;
        $this->methods = $methods;
        $this->annotations = $annotations;
    }

    public function getClassName()
    {
        return $this->className;
    }

    public function getAnnotations()
    {
        return $this->annotations;
    }

    public function serialize()
    {
        return serialize(array($this->className, $this->methods, $this->annotations));
    }

    public function unserialize($serialized)
    {
        list($this->className, $this->methods, $this->annotations) = unserialize($serialized);
    }
}
