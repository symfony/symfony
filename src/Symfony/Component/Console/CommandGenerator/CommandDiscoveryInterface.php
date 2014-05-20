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
 * Discovery Interface use by the manager for generates commands
 * from a given resource.
 *
 * @author Alberto Garcia Lamela <alberto.garcial@hotmail.com>
 *
 * @api
 */
Interface CommandDiscoveryInterface
{
    /**
     * Set the CommandFactory used for generating the command instances.
     *
     * @param CommandFactoryInterface $commandFactory
     */
    public function setCommandFactory(CommandFactoryInterface $commandFactory);

    /**
     * Use CommandResourceBuilderInterface for returning an array
     * given a source of definitions.
     *
     * @return array of definitions for building commands from.
     *
     * @api
     */
    public function buildDefinitions();

    /**
     * Generates a load of commands
     *
     * @return array of instances extending Command Class.
     *
     * @api
     */
    public function generateCommands();

    /**
     * Generates a single command.
     *
     * @param $singleCommandDefinition An array used by a custom Command class for creating a command.
     * @return instance extending Command Class.
     *
     * @api
     */
    public function generateCommand($singleCommandDefinition);

}
