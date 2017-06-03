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
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Adapter\RouteAnnotationAdapter;
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Exception\NoMatchingFactoryFoundException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Responsible for adapter creation of the Symfony Route annotation.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class RouteAnnotationAdapterFactory implements AnnotationAdapterFactoryInterface
{
    public function createForAnnotation($annotation)
    {
        if (!$annotation instanceof Route) {
            throw new NoMatchingFactoryFoundException(__CLASS__, Route::class);
        }

        return new RouteAnnotationAdapter($annotation);
    }
}
