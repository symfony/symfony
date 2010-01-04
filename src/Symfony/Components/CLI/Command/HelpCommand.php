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
 * HelpCommand displays the help for a given command.
 *
 * @package    symfony
 * @subpackage cli
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
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
        new Argument('command_name', Argument::OPTIONAL, 'The command name', 'help'),
        new Option('xml', null, Option::PARAMETER_NONE, 'To output help as XML'),
      ))
      ->setName('help')
      ->setDescription('Displays help for a command')
      ->setHelp(<<<EOF
The <info>help</info> command displays help for a given command:

  <info>./symfony help test:all</info>

You can also output the help as XML by using the <comment>--xml</comment> option:

  <info>./symfony help --xml test:all</info>
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
    if (null === $this->command)
    {
      $this->command = $this->application->getCommand($input->getArgument('command_name'));
    }

    if ($input->getOption('xml'))
    {
      $output->write($this->command->asXml(), Output::OUTPUT_RAW);
    }
    else
    {
      $output->write($this->command->asText());
    }
  }
}
