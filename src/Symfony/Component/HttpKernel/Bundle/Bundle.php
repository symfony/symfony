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
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * An implementation of BundleInterface that adds a few conventions
 * for DependencyInjection extensions and Console commands.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
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
     * It is only ever called once when the cache is empty.
     *
     * This method can be overridden to register compilation passes,
     * other extensions, ...
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function build(ContainerBuilder $container)
    {
    }

    /**
     * Returns the bundle's container extension.
     *
     * @return ExtensionInterface|null The container extension
     *
     * @throws \LogicException
     *
     * @api
     */
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $basename = preg_replace('/Bundle$/', '', $this->getName());

            $class = $this->getNamespace().'\\DependencyInjection\\'.$basename.'Extension';
            if (class_exists($class)) {
                $extension = new $class();

                // check naming convention
                $expectedAlias = Container::underscore($basename);
                if ($expectedAlias != $extension->getAlias()) {
                    throw new \LogicException(sprintf(
                        'The extension alias for the default extension of a '.
                        'bundle must be the underscored version of the '.
                        'bundle name ("%s" instead of "%s")',
                        $expectedAlias, $extension->getAlias()
                    ));
                }

                $this->extension = $extension;
            } else {
                $this->extension = false;
            }
        }

        if ($this->extension) {
            return $this->extension;
        }
    }

    /**
     * Gets the Bundle namespace.
     *
     * @return string The Bundle namespace
     *
     * @api
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
     *
     * @api
     */
    public function getPath()
    {
        if (null === $this->reflected) {
            $this->reflected = new \ReflectionObject($this);
        }

        return dirname($this->reflected->getFileName());
    }

    /**
     * Returns the bundle parent name.
     *
     * @return string The Bundle parent name it overrides or null if no parent
     *
     * @api
     */
    public function getParent()
    {
        return null;
    }

    /**
     * Returns the bundle name (the class short name).
     *
     * @return string The Bundle name
     *
     * @api
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
        if (!is_dir($dir = $this->getPath().'/Command')) {
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
            if ($r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command') && !$r->isAbstract() && !$r->getConstructor()->getNumberOfRequiredParameters()) {
                $application->add($r->newInstance());
            }
        }
    }
}
