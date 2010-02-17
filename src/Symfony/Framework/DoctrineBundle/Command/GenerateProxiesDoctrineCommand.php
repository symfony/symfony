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
 * 
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class GenerateProxiesDoctrineCommand extends DoctrineCommand
{
  /**
   * @see Command
   */
  protected function configure()
  {
    $this
      ->setName('doctrine:generate-proxies')
    ;
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $configuration = new Configuration();
    $configuration->setAttribute('em', $this->container->getDoctrine_Orm_ManagerService());

    $dirs = array();
    $bundleDirs = $this->container->getKernelService()->getBundleDirs();
    foreach ($this->container->getKernelService()->getBundles() as $bundle)
    {
      $tmp = dirname(str_replace('\\', '/', get_class($bundle)));
      $namespace = dirname($tmp);
      $class = basename($tmp);

      if (isset($bundleDirs[$namespace]) && is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/Model/Doctrine'))
      {
        $dirs[] = $dir;
      }
    }

    if (!is_dir($dir = $this->container->getParameter('kernel.cache_dir').'/doctrine/Proxies'))
    {
      mkdir($dir, 0777, true);
    }

    $cli = new DoctrineCliController($configuration);
    foreach ($dirs as $dir)
    {
      $cli->run(array('doctrine', 'orm:generate-proxies', '--class-dir='.$dir));
    }
  }
}
