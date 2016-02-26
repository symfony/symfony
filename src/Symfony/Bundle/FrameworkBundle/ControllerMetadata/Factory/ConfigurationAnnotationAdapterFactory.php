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
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Adapter\ConfigurationAnnotationAdapter;
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Configuration\ConfigurationAnnotation;
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Exception\UnsupportedAnnotationException;

/**
 * Responsible for adapter creation for the SensioFrameworkExtraBundle.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class ConfigurationAnnotationAdapterFactory implements AnnotationAdapterFactoryInterface
{
    public function createForAnnotation($annotation)
    {
        if (!$annotation instanceof ConfigurationAnnotation) {
            throw new UnsupportedAnnotationException(__CLASS__, get_class($annotation));
        }

        return new ConfigurationAnnotationAdapter($annotation);
    }
}
