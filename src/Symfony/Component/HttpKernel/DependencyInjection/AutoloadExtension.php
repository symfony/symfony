<?php

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Provides automatic loading of services.yml/xml files for Bundles with no Extension.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class AutoloadExtension extends Extension
{
    private $alias;
    private $path;

    /**
     * @param BundleInterface $bundle Bundle that owns this extension instance
     * @param string $alias The extension alias for this extension instance
     */
    public function __construct(BundleInterface $bundle, $alias)
    {
        $this->alias = $alias;
        $reflClass = new \ReflectionClass($bundle);
        $this->path = dirname($reflClass->getFileName());
    }

    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        foreach ($configs as $config) {
            if ($config) {
                throw new \UnexpectedValueException('The '.$this->alias.' extension accepts no configuration.');
            }
        }

        if (file_exists($this->path.'/Resources/config/services.yml')) {
            $loader = new YamlFileLoader($container, new FileLocator($this->path.'/Resources/config'));
            $loader->load('services.yml');
        } elseif (file_exists($this->path.'/Resources/config/services.xml')) {
            $loader = new XmlFileLoader($container, new FileLocator($this->path.'/Resources/config'));
            $loader->load('services.xml');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getAlias()
    {
        return $this->alias;
    }
}
