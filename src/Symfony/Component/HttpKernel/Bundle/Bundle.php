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
     * The default implementation automatically registers a DIC extension
     * if its name is the same as the bundle name after replacing the
     * Bundle suffix by Extension (DependencyInjection\SensioBlogExtension
     * for a SensioBlogBundle for instance). In such a case, the alias
     * is forced to be the underscore version of the bundle name
     * (sensio_blog for a SensioBlogBundle for instance).
     *
     * This method can be overridden to register compilation passes,
     * other extensions, ...
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function build(ContainerBuilder $container)
    {
        $class = $this->getNamespace().'\\DependencyInjection\\'.$this->getName().'Extension';
        if (class_exists($class)) {
            $extension = new $class();
            $alias = Container::underscore($this->getName());
            if ($alias !== $extension->getAlias()) {
                throw new \LogicException(sprintf('The extension alias for the default extension of a bundle must be the underscored version of the bundle name ("%s" vs "%s")', $alias, $extension->getAlias()));
            }

            $container->registerExtension($extension);
        }
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
     *
     * @throws RuntimeException If the bundle class name does not end with "Bundle"
     */
    final public function getName()
    {
        if (null !== $this->name) {
            return $this->name;
        }

        $fqcn = get_class($this);
        $name = false === ($pos = strrpos($fqcn, '\\')) ? $fqcn : substr($fqcn, $pos + 1);

        if ('Bundle' != substr($name, -6)) {
            throw new \RuntimeException(sprintf('The bundle class name "%s" must end with "Bundle" to be valid.', $name));
        }

        return $this->name = substr($name, 0, -6);
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
