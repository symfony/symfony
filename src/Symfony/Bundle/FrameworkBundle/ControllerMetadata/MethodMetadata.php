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
 * Responsible for storing metadata of a controller method.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class MethodMetadata implements \Serializable
{
    /**
     * @var string
     */
    private $methodName;

    /**
     * @var AnnotationAdapterInterface[]
     */
    private $annotations;

    /**
     * @param string                       $methodName
     * @param AnnotationAdapterInterface[] $annotations
     */
    public function __construct($methodName, array $annotations = [])
    {
        $this->methodName  = $methodName;
        $this->annotations = $annotations;
    }

    public function getMethodName()
    {
        return $this->methodName;
    }

    public function getAnnotations()
    {
        return $this->annotations;
    }

    public function serialize()
    {
        return serialize(array($this->methodName, $this->annotations));
    }

    public function unserialize($serialized)
    {
        list($this->methodName, $this->annotations) = unserialize($serialized);
    }
}
