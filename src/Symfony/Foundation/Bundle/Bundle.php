<?php

namespace Symfony\Foundation\Bundle;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\Console\Application;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Bundle.
 *
 * @package    Symfony
 * @subpackage Foundation
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class Bundle implements BundleInterface
{
    protected $name;
    protected $namespacePrefix;
    protected $path;
    protected $reflection;

    /**
     * Customizes the Container instance.
     *
     * @param Symfony\Components\DependencyInjection\ContainerInterface $container A ContainerInterface instance
     *
     * @return Symfony\Components\DependencyInjection\BuilderConfiguration A BuilderConfiguration instance
     */
    public function buildContainer(ContainerInterface $container)
    {
    }

    /**
     * Boots the Bundle.
     *
     * @param Symfony\Components\DependencyInjection\ContainerInterface $container A ContainerInterface instance
     */
    public function boot(ContainerInterface $container)
    {
    }

    /**
     * Shutdowns the Bundle.
     *
     * @param Symfony\Components\DependencyInjection\ContainerInterface $container A ContainerInterface instance
     */
    public function shutdown(ContainerInterface $container)
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
     * Registers the Commands for the console.
     *
     * @param Symfony\Components\Console\Application An Application instance
     */
    public function registerCommands(Application $application)
    {
        foreach ($application->getKernel()->getBundleDirs() as $dir) {
            $bundleBase = dirname(str_replace('\\', '/', get_class($this)));
            $commandDir = $dir.'/'.basename($bundleBase).'/Command';
            if (!is_dir($commandDir)) {
                continue;
            }

            // look for commands
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($commandDir), \RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
                if ($file->isDir() || substr($file, -4) !== '.php') {
                    continue;
                }

                $class = str_replace('/', '\\', $bundleBase).'\\Command\\'.str_replace(realpath($commandDir).'/', '', basename(realpath($file), '.php'));

                $r = new \ReflectionClass($class);

                if ($r->isSubclassOf('Symfony\\Components\\Console\\Command\\Command') && !$r->isAbstract()) {
                    $application->addCommand(new $class());
                }
            }
        }
    }

    protected function initReflection()
    {
        $tmp = dirname(str_replace('\\', '/', get_class($this)));
        $this->namespacePrefix = str_replace('/', '\\', dirname($tmp));
        $this->name = basename($tmp);
        $this->reflection = new \ReflectionObject($this);
        $this->path = dirname($this->reflection->getFilename());
    }
}
