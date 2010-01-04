<?php

namespace Symfony\Components\CLI\Tester;

use Symfony\Components\CLI\Task\Task;
use Symfony\Components\CLI\Input\ArrayInput;
use Symfony\Components\CLI\Output\StreamOutput;

class TaskTester
{
  protected $task;
  protected $display;
  protected $input;
  protected $output;

  /**
   * Constructor.
   *
   * @param Task $task A Task instance to test.
   */
  public function __construct(Task $task)
  {
    $this->task = $task;
  }

  /**
   * Executes the task.
   *
   * Available options:
   *
   *  * interactive: Sets the input interactive flag
   *  * decorated:   Sets the output decorated flag
   *  * verbosity:   Sets the output verbosity flag
   *
   * @param array $input   An array of arguments and options
   * @param array $options An array of options
   */
  public function execute(array $input, array $options = array())
  {
    $this->input = new ArrayInput(array_merge($input, array('task' => $this->task->getFullName())));
    if (isset($options['interactive']))
    {
      $this->input->setInteractive($options['interactive']);
    }

    $this->output = new StreamOutput(fopen('php://memory', 'w', false));
    if (isset($options['decorated']))
    {
      $this->output->setDecorated($options['decorated']);
    }
    if (isset($options['verbosity']))
    {
      $this->output->setVerbosity($options['verbosity']);
    }

    $ret = $this->task->run($this->input, $this->output);

    rewind($this->output->getStream());

    return $this->display = stream_get_contents($this->output->getStream());
  }

  /**
   * Gets the display returned by the last execution of the task.
   *
   * @return string The display
   */
  public function getDisplay()
  {
    return $this->display;
  }

  /**
   * Gets the input instance used by the last execution of the task.
   *
   * @return InputInterface The current input instance
   */
  public function getInput()
  {
    return $this->input;
  }

  /**
   * Gets the output instance used by the last execution of the task.
   *
   * @return OutputInterface The current output instance
   */
  public function getOutput()
  {
    return $this->output;
  }
}
