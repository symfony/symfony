<?php

use Symfony\Components\CLI\Task\Task;
use Symfony\Components\CLI\Input\InputInterface;
use Symfony\Components\CLI\Output\OutputInterface;

class FooTask extends Task
{
  public $input;
  public $output;

  protected function configure()
  {
    $this
      ->setName('foo:bar')
      ->setDescription('The foo:bar task')
      ->setAliases(array('afoobar'))
    ;
  }

  protected function interact(InputInterface $input, OutputInterface $output)
  {
    $output->write('interact called');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->input = $input;
    $this->output = $output;

    $output->write('called');
  }
}
