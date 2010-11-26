<?php

namespace Symfony\Component\HttpKernel\Bundle;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Finder\Finder;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * An implementation of the BundleInterface that follows a few conventions
 * for the DependencyInjection extensions and the Console commands. 
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Bundle extends ContainerAware implements BundleInterface
{
    protected $name;
    protected $namespacePrefix;
    protected $path;
    protected $reflection;

    /**
     * Boots the Bundle.
     */
    public function boot()
    {
    }

    /**
     * Shutdowns the Bundle.
     */
    public function shutdown()
    {
    }

    /**
     * Gets the Bundle name.
     *
     * @return string The Bundle name
     */
    public function getName()
    {
        if (null === $this->name) {
            $this->initReflection();
        }

        return $this->name;
    }

    /**
     * Gets the Bundle namespace prefix.
     *
     * @return string The Bundle namespace prefix
     */
    public function getNamespacePrefix()
    {
        if (null === $this->name) {
            $this->initReflection();
        }

        return $this->namespacePrefix;
    }

    /**
     * Gets the Bundle absolute path.
     *
     * @return string The Bundle absolute path
     */
    public function getPath()
    {
        if (null === $this->name) {
            $this->initReflection();
        }

        return $this->path;
    }

    /**
     * Gets the Bundle Reflection instance.
     *
     * @return \ReflectionObject A \ReflectionObject instance for the Bundle
     */
    public function getReflection()
    {
        if (null === $this->name) {
            $this->initReflection();
        }

        return $this->reflection;
    }

    /**
     * Finds and registers Dependency Injection Container extensions.
     *
     * Override this method if your DIC extensions do not follow the conventions:
     *
     * * Extensions are in the 'DependencyInjection/' sub-directory
     * * Extension class names ends with 'Extension'
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function registerExtensions(ContainerBuilder $container)
    {
        if (!$dir = realpath($this->getPath().'/DependencyInjection')) {
            return array();
        }

        $finder = new Finder();
        $finder->files()->name('*Extension.php')->in($dir);

        $prefix = $this->namespacePrefix.'\\'.$this->name.'\\DependencyInjection';
        foreach ($finder as $file) {
            $class = $prefix.strtr($file->getPath(), array($dir => '', '/' => '\\')).'\\'.basename($file, '.php');

            if ('Extension' === substr($class, -9)) {
                $container->registerExtension(new $class());
            }
        }
    }

    /**
     * Finds and registers Commands.
     *
     * Override this method if your bundle commands do not follow the conventions:
     *
     * * Commands are in the 'Command' sub-directory
     * * Commands extend Symfony\Component\Console\Command\Command
     *
     * @param Application $application An Application instance
     */
    public function registerCommands(Application $application)
    {
        if (!$dir = realpath($this->getPath().'/Command')) {
            return;
        }

        $finder = new Finder();
        $finder->files()->name('*Command.php')->in($dir);

        $prefix = $this->namespacePrefix.'\\'.$this->name.'\\Command';
        foreach ($finder as $file) {
            $r = new \ReflectionClass($prefix.strtr($file->getPath(), array($dir => '', '/' => '\\')).'\\'.basename($file, '.php'));
            if ($r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command') && !$r->isAbstract()) {
                $application->add($r->newInstance());
            }
        }
    }

    /**
     * Initializes the properties on this object that require a reflection
     * object to have been created.
     */
    protected function initReflection()
    {
        $tmp = dirname(str_replace('\\', '/', get_class($this)));
        $this->namespacePrefix = str_replace('/', '\\', dirname($tmp));
        $this->name = basename($tmp);
        $this->reflection = new \ReflectionObject($this);
        $this->path = dirname($this->reflection->getFilename());
    }
}
