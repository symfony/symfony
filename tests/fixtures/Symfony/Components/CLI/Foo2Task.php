<?php

use Symfony\Components\CLI\Task\Task;
use Symfony\Components\CLI\Input\InputInterface;
use Symfony\Components\CLI\Output\OutputInterface;

class Foo2Task extends Task
{
  protected function configure()
  {
    $this
      ->setName('foo1:bar')
      ->setDescription('The foo1:bar task')
      ->setAliases(array('afoobar2'))
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
  }
}
