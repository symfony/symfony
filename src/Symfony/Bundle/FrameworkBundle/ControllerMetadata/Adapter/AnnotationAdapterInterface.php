<?php
namespace Symfony\Bundle\FrameworkBundle\ControllerMetadata\Adapter;

use Doctrine\Common\Annotations\Annotation;

/**
 * Allows a custom adapter to be implemented.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
interface AnnotationAdapterInterface
{
    /**
     * The identifier for the annotation.
     *
     * @return string
     */
    public function getIdentifier();

    /**
     * The actual annotation found.
     *
     * @return mixed
     */
    public function getAnnotation();

    /**
     * Indicates whether the given annotation may be used multiple times on a given method or class.
     *
     * @return bool
     */
    public function allowMultiple();
}
