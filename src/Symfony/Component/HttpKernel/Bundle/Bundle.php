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

use Symfony\Component\Bundle\Bundle as BaseBundle;
use Symfony\Component\Bundle\CommandBundleService;
use Symfony\Component\Bundle\ContainerBundleService;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An implementation of BundleInterface that adds a few conventions
 * for DependencyInjection extensions and Console commands.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
abstract class Bundle extends BaseBundle implements BundleInterface
{
    protected $commandBundleService;
    protected $containerBuilderService;
    protected $extension;

    protected function getCommandBundleService()
    {
        if (null === $this->commandBundleService) {
            $this->commandBundleService = new CommandBundleService;
        }

        return $this->commandBundleService;
    }

    protected function getContainerBundleService()
    {
        if (null === $this->containerBuilderService) {
            $this->containerBuilderService = new ContainerBundleService;
        }

        return $this->containerBuilderService;
    }

    /**
     * Set command bundle service
     *
     * @param CommandBundleService $commandBundleService Command bundle service
     *
     * @return Bundle
     */
    public function setCommandBundleService(CommandBundleService $commandBundleService)
    {
        $this->commandBundleService = $commandBundleService;
    }

    /**
     * Set Container bundle service
     *
     * @param ContainerBundleService $containerBundleService Container bundle service
     *
     * @return Bundle
     */
    public function setContainerBundleService(ContainerBundleService $containerBundleService)
    {
        $this->containerBundleService = $containerBundleService;
    }

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $containerBundleService = $this->getContainerBundleService();

            $this->extension = $containerBundleService->getContainerExtension($this);
        }

        if ($this->extension) {
            return $this->extension;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function registerCommands(Application $application)
    {
        $commandBundleService = $this->getCommandBundleService();

        return $commandBundleService->registerCommands($this, $application);
    }
}
