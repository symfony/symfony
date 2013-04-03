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
 * ListCommand displays the list of all available commands for the application.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ListCommand extends AbstractDescriptorCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('list')
            ->setDescription('Lists commands')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lists all commands:

  <info>php %command.full_name%</info>

You can also display the commands for a specific namespace:

  <info>php %command.full_name% test</info>

You can also output the information as XML by using the <comment>--xml</comment> option:

  <info>php %command.full_name% --xml</info>

It's also possible to get raw list of commands (useful for embedding command runner):

  <info>php %command.full_name% --raw</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function createDefinition()
    {
        $definition = parent::createDefinition();
        $definition->addArgument(new InputArgument('namespace', InputArgument::OPTIONAL, 'The namespace name'));

        return $definition;
    }

    /**
     * {@inheritdoc}
     */
    protected function getNativeDefinition()
    {
        return $this->createDefinition();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->getHelper('descriptor')->describe($output, $this->getApplication(), $input->getOption('format'), $input->getOption('raw'));
    }
}
