<?php

namespace Symfony\Framework\WebBundle\Command;

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
 * 
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AssetsInstallCommand extends Command
{
  /**
   * @see Command
   */
  protected function configure()
  {
    $this
      ->setDefinition(array(
        new InputArgument('target', InputArgument::REQUIRED, 'The target directory'),
      ))
      ->setName('assets:install')
    ;
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    if (!is_dir($input->getArgument('target')))
    {
      throw new \InvalidArgumentException(sprintf('The target directory "%s" does not exist.', $input->getArgument('target')));
    }

    $filesystem = new Filesystem();

    $dirs = $this->container->getKernelService()->getBundleDirs();
    foreach ($this->container->getKernelService()->getBundles() as $bundle)
    {
      $tmp = dirname(str_replace('\\', '/', get_class($bundle)));
      $namespace = dirname($tmp);
      $class = basename($tmp);

      if (isset($dirs[$namespace]) && is_dir($originDir = $dirs[$namespace].'/'.$class.'/Resources/public'))
      {
        $output->writeln(sprintf('Installing assets for <comment>%s\\%s</comment>', $namespace, $class));

        $targetDir = $input->getArgument('target').'/bundles/'.preg_replace('/bundle$/', '', strtolower($class));

        $filesystem->remove($targetDir);
        mkdir($targetDir, 0755, true);
        $filesystem->mirror($originDir, $targetDir);
      }
    }
  }
}
