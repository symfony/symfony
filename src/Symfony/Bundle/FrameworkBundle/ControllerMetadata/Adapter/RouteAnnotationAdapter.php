<?php
namespace Symfony\Bundle\FrameworkBundle\ControllerMetadata\Adapter;

use Doctrine\Common\Annotations\Annotation;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Wraps the Route into a usable layer.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class RouteAnnotationAdapter implements AnnotationAdapterInterface
{
    /**
     * @var Route
     */
    private $annotation;

    /**
     * @param Route $annotation
     */
    public function __construct(Route $annotation)
    {
        $this->annotation = $annotation;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifier()
    {
        return $this->annotation->getName();
    }

    /**
     * {@inheritdoc}
     *
     * @returns Route
     */
    public function getAnnotation()
    {
        return $this->annotation;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool always true
     */
    public function allowMultiple()
    {
        return true;
    }
}
