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
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;

/**
 * ListCommand displays the list of all available commands for the application.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ListCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition($this->createDefinition())
            ->setName('list')
            ->setDescription('Lists commands')
            ->setHelp(<<<EOF
The <info>list</info> command lists all commands:

  <info>php app/console list</info>

You can also display the commands for a specific namespace:

  <info>php app/console list test</info>

You can also output the information as XML by using the <comment>--xml</comment> option:

  <info>php app/console list --xml</info>
EOF
            );
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
        if ($input->getOption('xml')) {
            $output->writeln($this->getApplication()->asXml($input->getArgument('namespace')), OutputInterface::OUTPUT_RAW);
        } else {
            $output->writeln($this->getApplication()->asText($input->getArgument('namespace')));
        }
    }

    private function createDefinition()
    {
        return new InputDefinition(array(
            new InputArgument('namespace', InputArgument::OPTIONAL, 'The namespace name'),
            new InputOption('xml', null, InputOption::VALUE_NONE, 'To output help as XML'),
        ));
    }
}
