<?php

use Symfony\Components\CLI\Command\Command;
use Symfony\Components\CLI\Input\InputInterface;
use Symfony\Components\CLI\Output\OutputInterface;

class FooCommand extends Command
{
  public $input;
  public $output;

  protected function configure()
  {
    $this
      ->setName('foo:bar')
      ->setDescription('The foo:bar command')
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
