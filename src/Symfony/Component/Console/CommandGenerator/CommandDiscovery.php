<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\CommandGenerator;

/**
 * Implementation of CommandDiscoveryInterface. Relies on CommandResourceBuilderInterface
 * and CommandFactoryInterface for discovering and returning a load of commands
 * from a given resource.
 *
 * @author Alberto Garcia Lamela <alberto.garcial@hotmail.com>
 *
 * @api
 */
class CommandDiscovery implements CommandDiscoveryInterface
{
    private $commandResourceBuilder;
    private $commandFactory;
    private $commandDefinitions;

    /**
     * Constructor.
     *
     * @param CommandResourceBuilderInterface $commandResourceBuilder
     * @param CommandFactoryInterface $commandFactory
     */
    public function __construct(CommandResourceBuilderInterface $commandResourceBuilder, CommandFactoryInterface $commandFactory = null)
    {
        $commandFactory == null ? new CommandDefaultFactory() : $commandFactory;
        $this->resourceBuilder = $commandResourceBuilder;
        $this->commandFactory = $commandFactory;
        $this->commandResourceBuilder = $commandResourceBuilder;
        $this->commandDefinitions = $this->buildDefinitions();

    }

    /**
     * {@inheritdoc}
     */
    public function setCommandFactory(CommandFactoryInterface $commandFactory)
    {
        $this->commandFactory = $commandFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function buildDefinitions()
    {
        $commandDefinitions = $this->commandResourceBuilder->buildDefinitions();

        return $commandDefinitions;
    }

    /**
     * {@inheritdoc}
     */
    public function generateCommands()
    {

        foreach ($this->commandDefinitions as $key => $singleCommandDefinition) {
            $commands[] = $this->generateCommand($singleCommandDefinition);
        }

        return $commands;
    }

    /**
     * {@inheritdoc}
     */
    public function generateCommand($singleCommandDefinition)
    {
        return $this->commandFactory->createCommand($singleCommandDefinition);
    }

}
