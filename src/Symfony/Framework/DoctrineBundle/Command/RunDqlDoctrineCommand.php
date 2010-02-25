<?php

namespace Symfony\Framework\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Framework\WebBundle\Util\Filesystem;
use Doctrine\Common\Cli\Configuration;
use Doctrine\Common\Cli\CliController as DoctrineCliController;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Execute a Doctrine DQL query and output the results
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class RunDqlDoctrineCommand extends DoctrineCommand
{
  /**
   * @see Command
   */
  protected function configure()
  {
    $this
      ->setName('doctrine:run-dql')
      ->setDescription('Executes arbitrary DQL directly from the command line.')
      ->addOption('dql', null, null, 'The DQL query to run.')
      ->addOption('depth', null, null, 'The depth to output the data to.')
      ->addOption('connection', null, null, 'The connection to use.')
    ;
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $options = $this->buildDoctrineCliTaskOptions($input, array(
      'dql', 'depth'
    ));
    $this->runDoctrineCliTask('orm:run-dql', $options);
  }
}