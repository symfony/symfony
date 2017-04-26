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
 * Manager class for setting the classes responsible for discovering
 * and generating commands objects from a given source.
 *
 * @author Alberto Garcia Lamela <alberto.garcial@hotmail.com>
 *
 * @api
 */
class CommandManager
{
    private $commandFactory;
    private $commandDiscovery;

    /**
     * Constructor.
     *
     * @param CommandDiscoveryInterface $commandDiscovery
     * @param $factoryReturningCommandClass
     * @param CommandFactoryInterface $commandFactory
     *
     * @api
     */
    public function __construct(CommandDiscoveryInterface $commandDiscovery, $factoryReturningCommandClass = '', CommandFactoryInterface $commandFactory = null)
    {
        $commandFactory = $commandFactory ? $commandFactory : new CommandDefaultFactory($factoryReturningCommandClass);
        $this->commandFactory = $commandFactory;
        $this->commandDiscovery = $commandDiscovery;
        $this->setCommandFactory();
    }

    /**
     * Set the factory class used by the discovery class
     * for creating new commands.
     *
     * @see generateCommand($singleCommandDefinition)
     */
    protected function setCommandFactory()
    {
        $this->commandDiscovery->setCommandFactory($this->commandFactory);
    }

    /**
     * @return array of instances extending Command Class.
     *
     * @api
     */
    public function generateCommands()
    {
        return $this->commandDiscovery->generateCommands();
    }

}
