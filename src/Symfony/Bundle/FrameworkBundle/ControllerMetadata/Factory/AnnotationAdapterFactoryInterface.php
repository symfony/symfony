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
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Exception\UnsupportedAnnotationException;

/**
 * Responsible for adapter creation.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
interface AnnotationAdapterFactoryInterface
{
    /**
     * @param mixed $annotation
     * @return AnnotationAdapterInterface
     *
     * @throws UnsupportedAnnotationException
     */
    public function createForAnnotation($annotation);
}
