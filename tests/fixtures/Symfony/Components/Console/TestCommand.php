<?php

use Symfony\Components\Console\Command\Command;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;

class TestCommand extends Command
{
  protected function configure()
  {
    $this
      ->setName('namespace:name')
      ->setAliases(array('name'))
      ->setDescription('description')
      ->setHelp('help')
    ;
  }

  public function mergeApplicationDefinition()
  {
    return parent::mergeApplicationDefinition();
  }

  public function getApplication()
  {
    return $this->application;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $output->write('execute called');
  }

  protected function interact(InputInterface $input, OutputInterface $output)
  {
    $output->write('interact called');
  }
}
