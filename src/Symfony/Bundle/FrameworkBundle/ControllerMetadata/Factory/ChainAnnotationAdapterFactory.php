<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\ControllerMetadata\Factory;

use Doctrine\Common\Annotations\Annotation;
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Adapter\AnnotationAdapterInterface;
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Exception\NoMatchingFactoryFoundException;
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Exception\UnsupportedAnnotationException;

/**
 * Allows different factories to try and create adapters for annotations.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class ChainAnnotationAdapterFactory implements AnnotationAdapterFactoryInterface
{
    /**
     * @var AnnotationAdapterFactoryInterface[]
     */
    private $factories;

    /**
     * @param AnnotationAdapterFactoryInterface[] $factories
     */
    public function __construct(array $factories)
    {
        $this->factories = $factories;
    }

    /**
     * Checks all registered annotation adapter factories until one is found that supports this annotation.
     *
     * @param mixed $annotation
     * @return AnnotationAdapterInterface
     *
     * @throws NoMatchingFactoryFoundException
     */
    public function createForAnnotation($annotation)
    {
        foreach ($this->factories as $factory) {
            try {
                return $factory->createForAnnotation($annotation);
            } catch (UnsupportedAnnotationException $e) {
                continue;
            }
        }

        throw new NoMatchingFactoryFoundException(get_class($annotation));
    }
}
