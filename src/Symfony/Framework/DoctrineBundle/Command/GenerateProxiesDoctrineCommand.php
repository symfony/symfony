<?php

namespace Symfony\Framework\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Framework\WebBundle\Util\Filesystem;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Generate the Doctrine ORM entity proxies to your cache directory.
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
      ->setDescription('Generates proxy classes for entity classes.')
    ;
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $dirs = array();
    $bundleDirs = $this->container->getKernelService()->getBundleDirs();
    foreach ($this->container->getKernelService()->getBundles() as $bundle)
    {
      $tmp = dirname(str_replace('\\', '/', get_class($bundle)));
      $namespace = str_replace('/', '\\', dirname($tmp));
      $class = basename($tmp);

      if (isset($bundleDirs[$namespace]) && is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/Entities'))
      {
        $dirs[] = $dir;
      }
    }

    if (!is_dir($dir = $this->container->getParameter('kernel.cache_dir').'/doctrine/Proxies'))
    {
      mkdir($dir, 0777, true);
    }

    foreach ($dirs as $dir)
    {
      $this->runDoctrineCliTask('orm:generate-proxies', array('class-dir' => $dir));
    }
  }
}
