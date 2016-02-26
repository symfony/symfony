<?php
namespace Symfony\Bundle\FrameworkBundle\ControllerMetadata\Adapter;

use Doctrine\Common\Annotations\Annotation;
use Symfony\Bundle\FrameworkBundle\ControllerMetadata\Configuration\ConfigurationAnnotation;

/**
 * Wraps the Annotation into a usable layer.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class ConfigurationAnnotationAdapter implements AnnotationAdapterInterface
{
    /**
     * @var ConfigurationAnnotation
     */
    private $annotation;

    /**
     * @param ConfigurationAnnotation $annotation
     */
    public function __construct(ConfigurationAnnotation $annotation)
    {
        $this->annotation = $annotation;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifier()
    {
        return $this->annotation->getAliasName();
    }

    /**
     * {@inheritdoc}
     *
     * @returns ConfigurationAnnotation
     */
    public function getAnnotation()
    {
        return $this->annotation;
    }

    /**
     * {@inheritdoc}
     */
    public function allowMultiple()
    {
        return $this->annotation->allowArray();
    }
}
