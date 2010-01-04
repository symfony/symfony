<?php

use Symfony\Components\CLI\Task\Task;
use Symfony\Components\CLI\Input\InputInterface;
use Symfony\Components\CLI\Output\OutputInterface;

class Foo1Task extends Task
{
  public $input;
  public $output;

  protected function configure()
  {
    $this
      ->setName('foo:bar1')
      ->setDescription('The foo:bar1 task')
      ->setAliases(array('afoobar1'))
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->input = $input;
    $this->output = $output;
  }
}
