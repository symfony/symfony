<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Bundle;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Console\Application;
use Symfony\Component\Finder\Finder;

/**
 * An implementation of BundleInterface that adds a few conventions
 * for DependencyInjection extensions and Console commands.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Bundle extends ContainerAware implements BundleInterface
{
    protected $name;
    protected $reflected;
    protected $extension;

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
     * Builds the bundle.
     *
     * It is only ever called when the cache is empty.
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function build(ContainerBuilder $container)
    {
    }

    /**
     * Returns the bundle's container extension.
     *
     * The returned extension will be implicitly loaded.
     *
     * @return ExtensionInterface|null The bundle's container extension
     */
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $class = $this->getNamespace().'\\DependencyInjection\\'.str_replace('Bundle', 'Extension', $this->getName());
            $this->extension = class_exists($class) ? new $class() : false;
        }

        return $this->extension ?: null;
    }

    /**
     * Gets the Bundle namespace.
     *
     * @return string The Bundle namespace
     */
    public function getNamespace()
    {
        if (null === $this->reflected) {
            $this->reflected = new \ReflectionObject($this);
        }

        return $this->reflected->getNamespaceName();
    }

    /**
     * Gets the Bundle directory path.
     *
     * @return string The Bundle absolute path
     */
    public function getPath()
    {
        if (null === $this->reflected) {
            $this->reflected = new \ReflectionObject($this);
        }

        return strtr(dirname($this->reflected->getFileName()), '\\', '/');
    }

    /**
     * Returns the bundle parent name.
     *
     * @return string The Bundle parent name it overrides or null if no parent
     */
    public function getParent()
    {
        return null;
    }

    /**
     * Returns the bundle name (the class short name).
     *
     * @return string The Bundle name
     */
    final public function getName()
    {
        if (null !== $this->name) {
            return $this->name;
        }

        $name = get_class($this);
        $pos = strrpos($name, '\\');

        return $this->name = false === $pos ? $name :  substr($name, $pos + 1);
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

        $prefix = $this->getNamespace().'\\Command';
        foreach ($finder as $file) {
            $ns = $prefix;
            if ($relativePath = $file->getRelativePath()) {
                $ns .= '\\'.strtr($relativePath, '/', '\\');
            }
            $r = new \ReflectionClass($ns.'\\'.$file->getBasename('.php'));
            if ($r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command') && !$r->isAbstract()) {
                $application->add($r->newInstance());
            }
        }
    }
}
