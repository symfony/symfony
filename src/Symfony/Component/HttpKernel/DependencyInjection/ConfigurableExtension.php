<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * This extension sub-class provides first-class integration with the
 * Config/Definition Component.
 *
 * You can use this as base class if you
 *
 *    a) use the Config/Definition component for configuration
 *    b) your configuration class is named "Configuration" and
 *    c) the configuration class resides in the DependencyInjection sub-folder
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class ConfigurableExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public final function load(array $configs, ContainerBuilder $container)
    {
        $this->loadInternal($this->processConfiguration($this->getConfiguration(array(), $container), $configs), $container);
    }

    /**
     * Configures the passed container according to the merged configuration.
     *
     * @param array $mergedConfig
     * @param ContainerBuilder $container
     */
    abstract protected function loadInternal(array $mergedConfig, ContainerBuilder $container);
}