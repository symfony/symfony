<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Loader;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Exception\FileLoaderLoadException;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
abstract class ConfigurableArrayLoader extends Loader
{
    private $configuration;

    public function __construct(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function load($resource, $type = null)
    {
        $processor = new Processor();
        try {
            $config = $processor->processConfiguration($this->configuration, array($resource));
        } catch (InvalidConfigurationException $e) {
            throw new FileLoaderLoadException($resource, null, null, $e);
        }

        $this->loadConfiguration($config);
    }

    abstract protected function loadConfiguration(array $config);

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return is_array($resource);
    }
}
