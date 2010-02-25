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
 * Database tool allows you to easily drop and create your configured databases.
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class DatabaseToolDoctrineCommand extends DoctrineCommand
{
  /**
   * @see Command
   */
  protected function configure()
  {
    $this
      ->setName('doctrine:database-tool')
      ->setDescription('Create and drop the configured databases.')
      ->addOption('re-create', null, null, 'Drop and re-create your databases.')
      ->addOption('drop', null, null, 'Drop your databases.')
      ->addOption('create', null, null, 'Create your databases.')
      ->addOption('connection', null, null, 'The connection name to work on.')
    ;
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    if ($input->getOption('re-create'))
    {
      $input->setOption('drop', true);
      $input->setOption('create', true);
    }
    if (!$input->getOption('drop') && !$input->getOption('create'))
    {
      throw new \InvalidArgumentException('You must specify one of the --drop and --create options or both.');
    }
    $found = false;
    $connections = $this->getDoctrineConnections();
    foreach ($connections as $name => $connection)
    {
      if ($input->getOption('connection') && $name != $input->getOption('connection'))
      {
        continue;
      }
      if ($input->getOption('drop'))
      {
        $this->dropDatabaseForConnection($connection, $output);
      }
      if ($input->getOption('create'))
      {
        $this->createDatabaseForConnection($connection, $output);
      }
      $found = true;
    }
    if ($found === false)
    {
      if ($input->getOption('connection'))
      {
        throw new \InvalidArgumentException(sprintf('<error>Could not find a connection named <comment>%s</comment></error>', $input->getOption('connection')));
      }
      else
      {
        throw new \InvalidArgumentException(sprintf('<error>Could not find any configured connections</error>', $input->getOption('connection')));        
      }
    }
  }

  protected function dropDatabaseForConnection(Connection $connection, OutputInterface $output)
  {
    $params = $connection->getParams();
    $name = isset($params['path']) ? $params['path']:$params['dbname'];

    try {
      $connection->getSchemaManager()->dropDatabase($name);
      $output->writeln(sprintf('<info>Dropped database for connection named <comment>%s</comment></info>', $name));
    } catch (\Exception $e) {
      $output->writeln(sprintf('<error>Could not drop database for connection named <comment>%s</comment></error>', $name));
      $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
    }
  }

  protected function createDatabaseForConnection(Connection $connection, OutputInterface $output)
  {
    $params = $connection->getParams();
    $name = isset($params['path']) ? $params['path']:$params['dbname'];

    unset($params['dbname']);

    $tmpConnection = \Doctrine\DBAL\DriverManager::getConnection($params);

    try {
      $tmpConnection->getSchemaManager()->createDatabase($name);
      $output->writeln(sprintf('<info>Created database for connection named <comment>%s</comment></info>', $name));
    } catch (\Exception $e) {
      $output->writeln(sprintf('<error>Could not create database for connection named <comment>%s</comment></error>', $name));
      $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
    }

    $tmpConnection->close();
  }
}