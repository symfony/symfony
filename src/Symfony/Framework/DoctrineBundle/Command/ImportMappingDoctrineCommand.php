<?php

namespace Symfony\Framework\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Import the initial mapping information for entities from an existing database
 * into a bundle.
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class ImportMappingDoctrineCommand extends DoctrineCommand
{
  /**
   * @see Command
   */
  protected function configure()
  {
    $this
      ->setName('doctrine:import-mapping')
      ->setDescription('Import the initial mapping information for entities from an existing database.')
      ->addOption('connection', null, InputOption::PARAMETER_REQUIRED, 'The connection import from.')
      ->addOption('bundle', null, InputOption::PARAMETER_REQUIRED, 'The bundle to import the mapping information to.')
      ->addOption('type', null, InputOption::PARAMETER_OPTIONAL, 'The mapping format type to generate (defaults to xml).', 'xml')
    ;
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    if (!preg_match('/Bundle$/', $bundle = $input->getOption('bundle')))
    {
      throw new \InvalidArgumentException('The bundle must end with Bundle.');
    }

    $dirs = $this->container->getKernelService()->getBundleDirs();

    $tmp = str_replace('\\', '/', $bundle);
    $namespace = str_replace('/', '\\', dirname($tmp));
    $bundle = basename($tmp);

    if (!isset($dirs[$namespace]))
    {
      throw new \InvalidArgumentException(sprintf('Could not find namespace "%s" for bundle "%s".', $namespace, $bundle));
    }

    $path = $dirs[$namespace].'/'.$bundle.'/Resources/config/doctrine/metadata';

    if (!is_dir($path))
    {
      mkdir($path, 0777, true);
    }

    $this->em = $this->container->getService(sprintf('doctrine.orm.%s_entity_manager', $input->getOption('connection')));
    $this->runCommand('doctrine:convert-mapping', array(
        '--from' => 'database',
        '--to' => $input->getOption('type'),
        '--dest' => $path
      )
    );
  }
}