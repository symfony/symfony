<?php

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\DelegatingLoader;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Provides useful features shared by many extensions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Extension implements ExtensionInterface
{
    protected $classes = array();
    protected $classMap = array();

    /**
     * Gets the classes to cache.
     *
     * @return array An array of classes
     */
    public function getClassesToCompile()
    {
        return $this->classes;
    }

    /**
     * Adds classes to the class cache.
     *
     * @param array $classes An array of classes
     */
    protected function addClassesToCompile(array $classes)
    {
        $this->classes = array_merge($this->classes, $classes);
    }

    /**
     * Gets the autoload class map.
     *
     * @return array An array of classes
     */
    public function getAutoloadClassMap()
    {
        return $this->classMap;
    }

    /**
     * Adds classes to the autoload class map.
     *
     * @param array $classes An array of classes
     */
    public function addClassesToAutoloadMap(array $classes)
    {
        $this->classMap = array_merge($this->classMap, $classes);
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return false;
    }

    /**
     * Returns the namespace to be used for this extension (XML namespace).
     *
     * @return string The XML namespace
     */
    public function getNamespace()
    {
        return false;
    }

    /**
     * Returns the recommended alias to use in XML.
     *
     * This alias is also the mandatory prefix to use when using YAML.
     *
     * @return string The alias
     */
    public function getAlias()
    {
        $className = get_class($this);
        if (substr($className, -9) != 'Extension') {
            throw new \BadMethodCallException('This extension does not follow the naming convention; you must overwrite the getAlias() method.');
        }
        $classBaseName = substr(strrchr($className, '\\'), 1, -9);

        return Container::underscore($classBaseName);
    }

    protected function getContainerLoader(ContainerBuilder $container, $paths)
    {
        $locator = new FileLocator($paths);
        $resolver = new LoaderResolver(array(
            new XmlFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new IniFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
            new ClosureLoader($container, $locator),
        ));

        return new DelegatingLoader($resolver);
    }
}
