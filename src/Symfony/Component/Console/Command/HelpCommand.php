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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * HelpCommand displays the help for a given command.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HelpCommand extends AbstractDescriptorCommand
{
    private $command;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->ignoreValidationErrors();

        $this
            ->setName('help')
            ->setDescription('Displays help for a command')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command displays help for a given command:

  <info>php %command.full_name% list</info>

You can also output the help as XML by using the <comment>--xml</comment> option:

  <info>php %command.full_name% --xml list</info>

To display the list of available commands, please use the <info>list</info> command.
EOF
            )
        ;
    }

    /**
     * Sets the command
     *
     * @param Command $command The command to set
     */
    public function setCommand(Command $command)
    {
        $this->command = $command;
    }

    /**
     * {@inheritdoc}
     */
    protected function createDefinition()
    {
        $definition = parent::createDefinition();
        
        $definition->addArgument(new InputArgument('command_name', InputArgument::OPTIONAL, 'The command name', 'help'));

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        if (null === $this->command) {
            $this->command = $this->getApplication()->find($input->getArgument('command_name'));
        }

        $this->getHelper('descriptor')->describe($output, $this->command, $input->getArgument('format'), $input->getOption('raw'));

        $this->command = null;
    }
}
