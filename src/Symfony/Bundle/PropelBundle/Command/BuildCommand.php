<?php

namespace Symfony\Bundle\PropelBundle\Command;

use Symfony\Bundle\PropelBundle\Command\PhingCommand;
use Symfony\Bundle\PropelBundle\Command\BuildModelCommand;
use Symfony\Bundle\PropelBundle\Command\BuildSqlCommand;
use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * BuildCommand.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class BuildCommand extends PhingCommand
{
    protected $additionalPhingArgs = array();

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDescription('Hub for Propel build commands (model, sql)')

            ->setDefinition(array(
                new InputOption('--classes', '', InputOption::PARAMETER_NONE, 'Build only classes'),
                new InputOption('--sql', '', InputOption::PARAMETER_NONE, 'Build only code'),
            ))
            ->setName('propel:build')
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When the target directory does not exist
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('sql')) {
            $output->writeln('<info>Building model classes</info>');
            $modelCommand = new BuildModelCommand();
            $modelCommand->setApplication($this->application);
            $modelCommand->execute($input, $output);
        }

        if (!$input->getOption('classes')) {
            $output->writeln('<info>Building model sql</info>');
            $sqlCommand = new BuildSQLCommand();
            $sqlCommand->setApplication($this->application);
            $sqlCommand->execute($input, $output);
        }
    }

}
