<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class to decorate a command.
 * For example, add behaviour before or after executing.
 *
 * @author Nico Schoenmaker<nschoenmaker@hostnet.nl>
 *
 * @api
 */
class CommandDecorator implements CommandInterface
{
    private $command;

    /**
     * @param CommandInterface $command
     */
    public function __construct(CommandInterface $command)
    {
        $this->command = $command;
    }

    /**
     * {@inheritdoc}
     */
    public function setApplication(Application $application = null)
    {
        return $this->command->setApplication($application);
    }

    /**
     * {@inheritdoc}
     */
    public function getHelperSet()
    {
        return $this->command->getHelperSet();
    }

    /**
     * {@inheritdoc}
     */
    public function getApplication()
    {
        return $this->command->getApplication();
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return $this->command->isEnabled();
    }

    /**
     * {@inheritdoc}
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        return $this->command->run($input, $output);
    }

    /**
     * {@inheritdoc}
     */
    public function mergeApplicationDefinition($mergeArgs = true)
    {
        return $this->command->mergeApplicationDefinition($mergeArgs);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        return $this->command->getDefinition();
    }

    /**
     * {@inheritdoc}
     */
    public function getNativeDefinition()
    {
        return $this->command->getNativeDefinition();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->command->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return $this->command->getDescription();
    }

    /**
     * {@inheritdoc}
     */
    public function getHelp()
    {
        return $this->command->getHelp();
    }

    /**
     * {@inheritdoc}
     */
    public function getProcessedHelp()
    {
        return $this->command->getProcessedHelp();
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return $this->command->getAliases();
    }

    /**
     * {@inheritdoc}
     */
    public function getSynopsis()
    {
        return $this->command->getSynopsis();
    }
}
