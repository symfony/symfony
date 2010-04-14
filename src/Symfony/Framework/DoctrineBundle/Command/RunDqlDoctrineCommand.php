<?php

namespace Symfony\Framework\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Doctrine\ORM\Tools\Console\Command\RunDqlCommand;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Execute a Doctrine DQL query and output the results.
 *
 * @package    Symfony
 * @subpackage Framework_DoctrineBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class RunDqlDoctrineCommand extends RunDqlCommand
{
  /**
   * @see Command
   */
  protected function configure()
  {
    parent::configure();
    $this->setName('doctrine:run-dql');
    $this->addOption('em', null, InputOption::PARAMETER_OPTIONAL, 'The entity manager to execute the DQL query on.');
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    DoctrineCommand::setApplicationEntityManager($this->application, $input->getOption('em'));

    return parent::execute($input, $output);
  }
}