<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\DependencyInjection;

use Symphony\Component\DependencyInjection\ContainerBuilder;

/**
 * This extension sub-class provides first-class integration with the
 * Config/Definition Component.
 *
 * You can use this as base class if
 *
 *    a) you use the Config/Definition component for configuration,
 *    b) your configuration class is named "Configuration", and
 *    c) the configuration class resides in the DependencyInjection sub-folder.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class ConfigurableExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    final public function load(array $configs, ContainerBuilder $container)
    {
        $this->loadInternal($this->processConfiguration($this->getConfiguration($configs, $container), $configs), $container);
    }

    /**
     * Configures the passed container according to the merged configuration.
     */
    abstract protected function loadInternal(array $mergedConfig, ContainerBuilder $container);
}
