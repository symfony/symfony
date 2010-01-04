<?php

use Symfony\Components\CLI\Command\Command;
use Symfony\Components\CLI\Input\InputInterface;
use Symfony\Components\CLI\Output\OutputInterface;

class Foo2Command extends Command
{
  protected function configure()
  {
    $this
      ->setName('foo1:bar')
      ->setDescription('The foo1:bar command')
      ->setAliases(array('afoobar2'))
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
  }
}
