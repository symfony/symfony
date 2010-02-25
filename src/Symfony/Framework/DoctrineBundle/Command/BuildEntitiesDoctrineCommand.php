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
 * Build all Bundle entity classes from mapping information.
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class BuildEntitiesDoctrineCommand extends DoctrineCommand
{
  /**
   * @see Command
   */
  protected function configure()
  {
    $this
      ->setName('doctrine:build-entities')
      ->setDescription('Build all Bundle entity classes from mapping information.')
    ;
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    foreach ($this->container->getParameter('kernel.bundle_dirs') as $bundle => $path)
    {
      $bundles = glob($path.'/*Bundle');
      foreach ($bundles as $p)
      {
        if (!is_dir($metadataPath = $p.'/Resources/config/doctrine/metadata'))
        {
          continue;
        }
        $opts = array();
        $opts['--from'] = $metadataPath;
        $opts['--to'] = 'annotation';
        $opts['--dest'] = realpath($path.'/..');
        $this->runCommand('doctrine:convert-mapping', $opts);
      }
    }
  }
}