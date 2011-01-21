<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Bundle;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Console\Application;
use Symfony\Component\Finder\Finder;

/**
 * An implementation of BundleInterface that adds a few conventions
 * for DependencyInjection extensions and Console commands.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Bundle extends ContainerAware implements BundleInterface
{
    protected $name;

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

        $pos = strrpos(get_class($this), '\\');

        return $this->name = substr(get_class($this), $pos ? $pos + 1 : 0);
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
            return;
        }

        $finder = new Finder();
        $finder->files()->name('*Extension.php')->in($dir);

        $prefix = $this->getNamespace().'\\DependencyInjection';
        foreach ($finder as $file) {
            $class = $prefix.strtr($file->getPath(), array($dir => '', '/' => '\\')).'\\'.$file->getBasename('.php');

            $container->registerExtension(new $class());
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

        $prefix = $this->getNamespace().'\\Command';
        foreach ($finder as $file) {
            $r = new \ReflectionClass($prefix.strtr($file->getPath(), array($dir => '', '/' => '\\')).'\\'.$file->getBasename('.php'));
            if ($r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command') && !$r->isAbstract()) {
                $application->add($r->newInstance());
            }
        }
    }
}
