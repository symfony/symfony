<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Extension;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Extension is a helper class that helps organize extensions better.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Extension implements ExtensionInterface
{
    /**
     * Loads a specific configuration.
     *
     * @param string  $tag           The tag name
     * @param array   $config        An array of configuration values
     * @param ContainerBuilder $configuration A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load($tag, array $config, ContainerBuilder $configuration)
    {
        if (!method_exists($this, $method = $tag.'Load')) {
            throw new \InvalidArgumentException(sprintf('The tag "%s:%s" is not defined in the "%s" extension.', $this->getAlias(), $tag, $this->getAlias()));
        }

        $this->$method($config, $configuration);
    }
}
