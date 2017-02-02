<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\ControllerMetadata;

use Symfony\Component\HttpKernel\ControllerMetadata\Configuration\ConfigurationFactoryInterface;

/**
 * Builds {@see ControllerMetadata} objects based on the given Controller.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class ControllerMetadataFactory implements ControllerMetadataFactoryInterface
{
    private $argumentMetadataFactory;
    private $configurationFactory;

    public function __construct(ArgumentMetadataFactoryInterface $argumentMetadataFactory, ConfigurationFactoryInterface $configurationFactory = null)
    {
        $this->argumentMetadataFactory = $argumentMetadataFactory;
        $this->configurationFactory = $configurationFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function createControllerMetadata(callable $controller)
    {
        if (null === ($logicalName = ControllerMetadataUtil::getControllerLogicalName($controller))) {
            return;
        }

        list($className, $method) = $logicalName;

        $arguments = $this->argumentMetadataFactory->createArgumentMetadata($controller);
        $configurations = null !== $this->configurationFactory ? $this->configurationFactory->createConfiguration($className, $method) : array();

        return new ControllerMetadata($className, $method, $arguments, $configurations);
    }
}
