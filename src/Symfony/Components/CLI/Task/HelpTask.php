<?php

namespace Symfony\Components\CLI\Task;

use Symfony\Components\CLI\Input\Definition;
use Symfony\Components\CLI\Input\Argument;
use Symfony\Components\CLI\Input\Option;
use Symfony\Components\CLI\Input\InputInterface;
use Symfony\Components\CLI\Output\OutputInterface;
use Symfony\Components\CLI\Output\Output;
use Symfony\Components\CLI\Task\Task;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * HelpTask displays the help for a given task.
 *
 * @package    symfony
 * @subpackage cli
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HelpTask extends Task
{
  protected $task;

  /**
   * @see Task
   */
  protected function configure()
  {
    $this->ignoreValidationErrors = true;

    $this
      ->setDefinition(array(
        new Argument('task_name', Argument::OPTIONAL, 'The task name', 'help'),
        new Option('xml', null, Option::PARAMETER_NONE, 'To output help as XML'),
      ))
      ->setName('help')
      ->setDescription('Displays help for a task')
      ->setHelp(<<<EOF
The <info>help</info> task displays help for a given task:

  <info>./symfony help test:all</info>

You can also output the help as XML by using the <comment>--xml</comment> option:

  <info>./symfony help --xml test:all</info>
EOF
      );
  }

  public function setTask(Task $task)
  {
    $this->task = $task;
  }

  /**
   * @see Task
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    if (null === $this->task)
    {
      $this->task = $this->application->getTask($input->getArgument('task_name'));
    }

    if ($input->getOption('xml'))
    {
      $output->write($this->task->asXml(), Output::OUTPUT_RAW);
    }
    else
    {
      $output->write($this->task->asText());
    }
  }
}
