<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Bundle\CommandRegisterInterface;

/**
 * Class BundleImplementsRegisterCommands
 *
 * @author  Yannick Voyer (http://github.com/yvoyer)
 *
 * @package Symfony\Bundle\FrameworkBundle\Tests\Fixtures
 */
class BundleImplementsRegisterCommands implements BundleInterface, CommandRegisterInterface
{
    /**
     * Boots the Bundle.
     *
     * @api
     */
    public function boot()
    {
    }

    /**
     * Shutdowns the Bundle.
     *
     * @api
     */
    public function shutdown()
    {
    }

    /**
     * Builds the bundle.
     *
     * It is only ever called once when the cache is empty.
     *
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @api
     */
    public function build(ContainerBuilder $container)
    {
    }

    /**
     * Returns the container extension that should be implicitly loaded.
     *
     * @return ExtensionInterface|null The default extension or null if there is none
     *
     * @api
     */
    public function getContainerExtension()
    {
    }

    /**
     * Returns the bundle name that this bundle overrides.
     *
     * Despite its name, this method does not imply any parent/child relationship
     * between the bundles, just a way to extend and override an existing
     * bundle.
     *
     * @return string The Bundle name it overrides or null if no parent
     *
     * @api
     */
    public function getParent()
    {
    }

    /**
     * Returns the bundle name (the class short name).
     *
     * @return string The Bundle name
     *
     * @api
     */
    public function getName()
    {
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
    }

    /**
     * Gets the Bundle directory path.
     *
     * The path should always be returned as a Unix path (with /).
     *
     * @return string The Bundle absolute path
     *
     * @api
     */
    public function getPath()
    {
    }

    /**
     * Registers custom Commands.
     *
     * @param Application $application An Application instance
     */
    public function registerCommands(Application $application)
    {
        $command = new Command('my-custom-command');

        $application->add($command);
    }

    /**
     * Sets the Container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     *
     * @api
     */
    public function setContainer(ContainerInterface $container = null)
    {
    }
}
 