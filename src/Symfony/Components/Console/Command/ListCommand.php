<?php

namespace Symfony\Components\Console\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Components\Console\Command\Command;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ListCommand displays the list of all available commands for the application.
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ListCommand extends Command
{
  /**
   * @see Command
   */
  protected function configure()
  {
    $this
      ->setDefinition(array(
        new InputArgument('namespace', InputArgument::OPTIONAL, 'The namespace name'),
        new InputOption('xml', null, InputOption::PARAMETER_NONE, 'To output help as XML'),
      ))
      ->setName('list')
      ->setDescription('Lists commands')
      ->setHelp(<<<EOF
The <info>list</info> command lists all commands:

  <info>./symfony list</info>

You can also display the commands for a specific namespace:

  <info>./symfony list test</info>

You can also output the information as XML by using the <comment>--xml</comment> option:

  <info>./symfony list --xml</info>
EOF
      );
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    if ($input->getOption('xml'))
    {
      $output->writeln($this->application->asXml($input->getArgument('namespace')), Output::OUTPUT_RAW);
    }
    else
    {
      $output->writeln($this->application->asText($input->getArgument('namespace')));
    }
  }
}
