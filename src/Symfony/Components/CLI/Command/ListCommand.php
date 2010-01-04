<?php

namespace Symfony\Components\CLI\Command;

use Symfony\Components\CLI\Input\Definition;
use Symfony\Components\CLI\Input\Argument;
use Symfony\Components\CLI\Input\Option;
use Symfony\Components\CLI\Input\InputInterface;
use Symfony\Components\CLI\Output\OutputInterface;
use Symfony\Components\CLI\Output\Output;
use Symfony\Components\CLI\Command\Command;

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
 * @subpackage cli
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
        new Argument('namespace', Argument::OPTIONAL, 'The namespace name'),
        new Option('xml', null, Option::PARAMETER_NONE, 'To output help as XML'),
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
      $output->write($this->application->asXml($input->getArgument('namespace')), Output::OUTPUT_RAW);
    }
    else
    {
      $output->write($this->application->asText($input->getArgument('namespace')));
    }
  }
}
