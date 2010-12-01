<?php

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Command\Command;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * HelpCommand displays the help for a given command.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HelpCommand extends Command
{
    protected $command;

    /**
     * @see Command
     */
    protected function configure()
    {
        $this->ignoreValidationErrors = true;

        $this
            ->setDefinition(array(
                new InputArgument('command_name', InputArgument::OPTIONAL, 'The command name', 'help'),
                new InputOption('xml', null, InputOption::VALUE_NONE, 'To output help as XML'),
            ))
            ->setName('help')
            ->setAliases(array('?'))
            ->setDescription('Displays help for a command')
            ->setHelp(<<<EOF
The <info>help</info> command displays help for a given command:

  <info>./symfony help list</info>

You can also output the help as XML by using the <comment>--xml</comment> option:

  <info>./symfony help --xml list</info>
EOF
            );
    }

    public function setCommand(Command $command)
    {
        $this->command = $command;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (null === $this->command) {
            $this->command = $this->application->get($input->getArgument('command_name'));
        }

        if ($input->getOption('xml')) {
            $output->writeln($this->command->asXml(), Output::OUTPUT_RAW);
        } else {
            $output->writeln($this->command->asText());
        }
    }
}
