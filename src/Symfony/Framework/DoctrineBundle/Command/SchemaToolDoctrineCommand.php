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
 * Create, drop, and update your Doctrine ORM schema in the DBMS.
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class SchemaToolDoctrineCommand extends DoctrineCommand
{
  /**
   * @see Command
   */
  protected function configure()
  {
    $this
      ->setName('doctrine:schema-tool')
      ->setDescription('Processes the schema and either apply it directly on EntityManager or generate the SQL output.')
      ->addOption('create', null, null, 'Create your database schema.')
      ->addOption('drop', null, null, 'Drop your database schema.')
      ->addOption('update', null, null, 'Update your database schema and add anything that is not in your database but exists in your schema.')
      ->addOption('complete-update', null, null, 'Complete update and drop anything that is not in your schema.')
      ->addOption('re-create', null, null, 'Drop and re-create your database schema.')
      ->addOption('dump-sql', null, null, 'Dump the SQL instead of executing it.')
      ->addOption('connection', null, null, 'The connection to use.')
    ;
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $options = $this->buildDoctrineCliTaskOptions($input, array(
      'create', 'drop', 'update', 'complete-update', 're-create', 'dump-sql'
    ));

    $entityDirs = $this->container->getParameter('doctrine.orm.entity_dirs');
    $options['class-dir'] = implode(', ', $entityDirs);

    $found = false;
    $ems = $this->getDoctrineEntityManagers();
    foreach  ($ems as $name => $em)
    {
      if ($input->getOption('connection') && $name !== $input->getOption('connection'))
      {
        continue;
      }
      $this->em = $em;
      $this->runDoctrineCliTask('orm:schema-tool', $options);
      $found = true;
    }

    if ($found === false)
    {
      if ($input->getOption('connection'))
      {
        $output->writeln(sprintf('<error>Could not find a connection named <comment>%s</comment></error>', $input->getOption('connection')));
      }
      else
      {
        $output->writeln(sprintf('<error>Could not find any configured connections</error>', $input->getOption('connection')));        
      }
    }
  }
}