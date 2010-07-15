<?php

namespace Symfony\Framework\Bundle;

use Symfony\Components\DependencyInjection\ContainerInterface;
use Symfony\Components\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Components\Console\Application;
use Symfony\Components\Finder\Finder;

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
 * @subpackage Framework
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
     * @param \Symfony\Components\DependencyInjection\ParameterBag\ParameterBagInterface $parameterBag A ParameterBagInterface instance
     *
     * @return \Symfony\Components\DependencyInjection\ContainerBuilder A ContainerBuilder instance
     */
    public function buildContainer(ParameterBagInterface $parameterBag)
    {
    }

    /**
     * Boots the Bundle.
     *
     * @param \Symfony\Components\DependencyInjection\ContainerInterface $container A ContainerInterface instance
     */
    public function boot(ContainerInterface $container)
    {
    }

    /**
     * Shutdowns the Bundle.
     *
     * @param \Symfony\Components\DependencyInjection\ContainerInterface $container A ContainerInterface instance
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
     * Finds and registers commands for the current bundle.
     *
     * @param Symfony\Components\Console\Application $application An Application instance
     */
    public function registerCommands(Application $application)
    {
        if (!is_dir($dir = $this->getPath().'/Command')) {
            return;
        }

        $finder = new Finder();
        $finder->files()->name('*Command.php')->in($dir);

        $prefix = $this->namespacePrefix.'\\'.$this->name.'\\Command\\';
        foreach ($finder as $file) {
            $r = new \ReflectionClass($prefix.basename($file, '.php'));
            if ($r->isSubclassOf('Symfony\\Components\\Console\\Command\\Command') && !$r->isAbstract()) {
                $application->addCommand($r->newInstance());
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
