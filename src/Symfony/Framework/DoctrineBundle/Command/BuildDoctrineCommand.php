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
use Doctrine\DBAL\Connection;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Build command allows you to easily build and re-build your Doctrine development environment
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 * @author     Kris Wallsmith <kris.wallsmith@symfony-project.org>
 */
class BuildDoctrineCommand extends DoctrineCommand
{
  const
    BUILD_ENTITIES   = 1,
    BUILD_DB         = 16,

    OPTION_ENTITIES    = 1,
    OPTION_DB          = 16,
    OPTION_ALL         = 31;

  /**
   * @see Command
   */
  protected function configure()
  {
    $this
      ->setName('doctrine:build')
      ->setDescription('Build task for easily re-building your Doctrine development environment.')
      ->addOption('all', null, null, 'Build everything and reset the database')
      ->addOption('entities', null, null, 'Build model classes')
      ->addOption('db', null, null, 'Drop database, create database and create schema.')
      ->addOption('and-load', null, InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY, 'Load data fixtures')
      ->addOption('and-append', null, InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY, 'Load data fixtures and append to existing data')
      ->addOption('and-update-schema', null, null, 'Update schema after rebuilding all classes')
      ->addOption('connection', null, null, 'The connection to use.')
      ->setHelp('
The <info>doctrine:build</info> task builds your Doctrine development environment.

  <info>php console doctrine:build --all</info>

The above command will re-build your entities and re-create your database schema.

If you wanted to only update your schema instead of re-creating it you can run
the following:

  <info>php console doctrine:build --entities --and-update-schema</info>

Now your entities are re-built and your database schema is up to date!

You can also use the <info>--and-load</info> and <info>--and-append</info> to
load data fixtures after running another build option.

  <info>php console doctrine:build --all --and-load</info>

The above will re-build everything and load all bundle data fixtures.
')
    ;
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    if (!$mode = $this->calculateMode($input))
    {
      throw new \InvalidArgumentException(sprintf("You must include one or more of the following build options:\n--%s\n\nSee this task's help page for more information:\n\n  php console help doctrine:build", join(', --', array_keys($this->getBuildOptions()))));
    }

    if (self::BUILD_ENTITIES == (self::BUILD_ENTITIES & $mode))
    {
      $this->runCommand('doctrine:build-entities');
    }

    if (self::BUILD_DB == (self::BUILD_DB & $mode))
    {
      $this->runCommand('doctrine:schema-tool', array('--re-create' => true, '--connection' => $input->getOption('connection')));
    }

    if ($input->getOption('and-update-schema'))
    {
      $this->runCommand('doctrine:schema-tool', array('--update' => true, '--connection' => $input->getOption('connection')));
      $this->runCommand('doctrine:schema-tool', array('--complete-update' => true,  '--connection' => $input->getOption('connection')));
    }

    if ($input->hasOption('and-load'))
    {
      $dirOrFile = $input->getOption('and-load');
      $this->runCommand('doctrine:load-data-fixtures', 
        array('--dir_or_file' => $dirOrFile, '--append' => false)
      );
    }
    else if ($input->hasOption('and-append'))
    {
      $dirOrFile = $input->getOption('and-append');
      $this->runCommand('doctrine:load-data-fixtures', array('--dir_or_file' => $dirOrFile, '--append' => true));
    }
  }

  /**
   * Calculates a bit mode based on the supplied options.
   *
   * @param InputInterface $input
   * @return integer
   */
  protected function calculateMode(InputInterface $input)
  {
    $mode = 0;
    foreach ($this->getBuildOptions() as $name => $value)
    {
      if ($input->getOption($name) === true)
      {
        $mode = $mode | $value;
      }
    }

    return $mode;
  }

  /**
   * Returns an array of valid build options.
   *
   * @return array An array of option names and their mode
   */
  protected function getBuildOptions()
  {
    $options = array();
    foreach ($this->getDefinition()->getOptions() as $option)
    {
      if (defined($constant = __CLASS__.'::OPTION_'.str_replace('-', '_', strtoupper($option->getName()))))
      {
        $options[$option->getName()] = constant($constant);
      }
    }

    return $options;
  }
}