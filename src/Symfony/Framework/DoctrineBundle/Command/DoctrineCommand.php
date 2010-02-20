<?php

namespace Symfony\Framework\DoctrineBundle\Command;

use Symfony\Framework\WebBundle\Command\Command;
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
 * Base class for Doctrine consol commands to extend from.
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class DoctrineCommand extends Command
{
  protected $cli;

  protected function getDoctrineCli()
  {
    if ($this->cli === null)
    {
      $configuration = new Configuration();
      $configuration->setAttribute('em', $this->container->getDoctrine_Orm_EntityManagerService());
      $this->cli = new DoctrineCliController($configuration);
    }
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

  public function buildDoctrineCliTaskOptions(InputInterface $input, array $options)
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
}