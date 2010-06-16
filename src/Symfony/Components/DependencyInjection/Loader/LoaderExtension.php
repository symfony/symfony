<?php

namespace Symfony\Components\DependencyInjection\Loader;

use Symfony\Components\DependencyInjection\BuilderConfiguration;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * LoaderExtension is a helper class that helps organize extensions better.
 *
 * @package    Symfony
 * @subpackage Components_DependencyInjection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class LoaderExtension implements LoaderExtensionInterface
{
    protected $resources = array();

    /**
     * Sets a configuration entry point for the given extension name.
     *
     * @param string The configuration extension name
     * @param mixed  A resource
     */
    public function setConfiguration($name, $resource)
    {
        $this->resources[$name] = $resource;
    }

    /**
     * Loads a specific configuration.
     *
     * @param string               $tag           The tag name
     * @param array                $config        An array of configuration values
     * @param BuilderConfiguration $configuration A BuilderConfiguration instance
     *
     * @return BuilderConfiguration A BuilderConfiguration instance
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load($tag, array $config, BuilderConfiguration $configuration)
    {
        if (!method_exists($this, $method = $tag.'Load')) {
            throw new \InvalidArgumentException(sprintf('The tag "%s:%s" is not defined in the "%s" extension.', $this->getAlias(), $tag, $this->getAlias()));
        }

        return $this->$method($config, $configuration);
    }
}
