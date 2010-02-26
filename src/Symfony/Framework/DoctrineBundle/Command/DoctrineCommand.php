<?php

namespace Symfony\Framework\DoctrineBundle\Command;

use Symfony\Framework\WebBundle\Command\Command;
use Symfony\Components\Console\Input\ArrayInput;
use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Framework\WebBundle\Console\Application;
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
 * Base class for Doctrine consol commands to extend from.
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class DoctrineCommand extends Command
{
  protected
    $application,
    $cli,
    $em;

  protected function getDoctrineCli()
  {
    if ($this->cli === null)
    {
      $configuration = new Configuration();
      $this->cli = new DoctrineCliController($configuration);
    }
    $em = $this->em ? $this->em : $this->container->getDoctrine_Orm_EntityManagerService();
    $this->cli->getConfiguration()->setAttribute('em', $em);
    return $this->cli;
  }

  protected function runDoctrineCliTask($name, $options = array())
  {
    $builtOptions = array();
    foreach ($options as $key => $value)
    {
      if ($value === null)
      {
        $builtOptions[] = sprintf('--%s', $key);
      }
      else
      {
        $builtOptions[] = sprintf('--%s=%s', $key, $value);
      }
    }
    return $this->getDoctrineCli()->run(array_merge(array('doctrine', $name), $builtOptions));
  }

  protected function buildDoctrineCliTaskOptions(InputInterface $input, array $options)
  {
    $taskOptions = array();
    foreach ($options as $option)
    {
      if ($value = $input->getOption($option))
      {
        $options[$option] = $value;
      }
    }
    return $options;
  }

  protected function runCommand($name, array $input = array())
  {
    if ($this->application === null)
    {
      $this->application = new Application($this->container->getKernelService());
    }

    $arguments = array();
    $arguments = array_merge(array($name), $input);
    $input = new ArrayInput($arguments);
    $this->application->setAutoExit(false);
    $this->application->run($input);
  }

  /**
   * TODO: Better way to do these functions?
   */
  protected function getDoctrineConnections()
  {
    $connections = array();
    $ids = $this->container->getServiceIds();
    foreach ($ids as $id)
    {
      preg_match('/doctrine.dbal.(.*)_connection/', $id, $matches);
      if ($matches)
      {
        $name = $matches[1];
        $connections[$name] = $this->container->getService($id);
      }
    }
    return $connections;
  }

  protected function getDoctrineEntityManagers()
  {
    $entityManagers = array();
    $ids = $this->container->getServiceIds();
    foreach ($ids as $id)
    {
      preg_match('/doctrine.orm.(.*)_entity_manager/', $id, $matches);
      if ($matches)
      {
        $name = $matches[1];
        $entityManagers[$name] = $this->container->getService($id);
      }
    }
    return $entityManagers;
  }
}