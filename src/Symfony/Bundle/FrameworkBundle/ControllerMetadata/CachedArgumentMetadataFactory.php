<?php

namespace Symfony\Bundle\FrameworkBundle\ControllerMetadata;

use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactoryInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ControllerMetadataFactoryInterface;

/**
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class CachedArgumentMetadataFactory implements ArgumentMetadataFactoryInterface
{
    private $baseFactory;
    private $controllerMetadata;

    public function __construct(ArgumentMetadataFactoryInterface $baseFactory, ControllerMetadataFactoryInterface $controllerMetadata)
    {
        $this->baseFactory = $baseFactory;
        $this->controllerMetadata = $controllerMetadata;
    }

    /**
     * {@inheritdoc}
     */
    public function createArgumentMetadata($controller)
    {
        if (null !== ($metadata = $this->controllerMetadata->createControllerMetadata($controller))) {
            return $metadata->getArguments();
        }

        return $this->baseFactory->createArgumentMetadata($controller);
    }
}
