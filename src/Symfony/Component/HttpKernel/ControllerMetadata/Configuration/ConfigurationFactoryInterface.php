<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\ControllerMetadata\Configuration;

/**
 * Responsible for the creation of controller action configuration.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
interface ConfigurationFactoryInterface
{
    /**
     * @param string $className
     * @param string $method
     *
     * @return ConfigurationInterface[]
     */
    public function createConfiguration($className, $method);
}
