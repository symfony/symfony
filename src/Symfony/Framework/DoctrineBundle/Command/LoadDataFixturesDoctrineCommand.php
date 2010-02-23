<?php

namespace Symfony\Framework\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Framework\WebBundle\Util\Filesystem;
use Symfony\Framework\WebBundle\Util\Finder;
use Doctrine\Common\Cli\Configuration;
use Doctrine\Common\Cli\CliController as DoctrineCliController;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Internal\CommitOrderCalculator;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Load data fixtures from bundles
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class LoadDataFixturesDoctrineCommand extends DoctrineCommand
{
  /**
   * @see Command
   */
  protected function configure()
  {
    $this
      ->setName('doctrine:load-data-fixtures')
      ->setDescription('Load data fixtures to your database.')
      ->addOption('dir_or_file', null, null, 'The directory or file to load data fixtures from.')
      ->addOption('append', null, InputOption::PARAMETER_OPTIONAL, 'Whether or not to append the data fixtures.', false)
    ;
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $em = $this->container->getDoctrine_ORM_EntityManagerService();
    if (!$input->getOption('append'))
    {
      $classes = array();
      $metadatas = $em->getMetadataFactory()->getAllMetadata();

      foreach ($metadatas as $metadata)
      {
        if (!$metadata->isMappedSuperclass)
        {
          $classes[] = $metadata;
        }
      }
      $cmf = $em->getMetadataFactory();
      $classes = $this->getCommitOrder($em, $classes);
      for ($i = count($classes) - 1; $i >= 0; --$i)
      {
        $class = $classes[$i];
        if ($cmf->hasMetadataFor($class->name))
        {
          try {
            $em->createQuery('DELETE FROM '.$class->name.' a')->execute();
          } catch (Exception $e) {}
        }
      }
    }

    $dirOrFile = $input->getOption('dir_or_file');
    if ($dirOrFile)
    {
      $paths = $dirOrFile;
    } else {
      $paths = array();
      $bundleDirs = $this->container->getKernelService()->getBundleDirs();
      foreach ($this->container->getKernelService()->getBundles() as $bundle)
      {
        $tmp = dirname(str_replace('\\', '/', get_class($bundle)));
        $namespace = str_replace('/', '\\', dirname($tmp));
        $class = basename($tmp);

        if (isset($bundleDirs[$namespace]) && is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/Resources/data/fixtures/doctrine'))
        {
          $paths[] = $dir;
        }
      }
    }

    $files = array();
    foreach ($paths as $path)
    {
      if (is_dir($path))
      {
        $found = Finder::type('file')
          ->name('*.php')
          ->in($path);
      } else {
        $found = array($path);
      }
      $files = array_merge($files, $found);
    }

    $files = array_unique($files);

    foreach ($files as $file)
    {
      $before = array_keys(get_defined_vars());
      include($file);
      $after = array_keys(get_defined_vars());
      $new = array_diff($after, $before);
      $entities = array_values($new);
      unset($entities[array_search('before', $entities)]);
      foreach ($entities as $entity) {
        $em->persist($$entity);
      }
      $em->flush();
    }
  }
  
  protected function getCommitOrder(EntityManager $em, array $classes)
  {
    $calc = new CommitOrderCalculator;

    foreach ($classes as $class)
    {
      $calc->addClass($class);

      foreach ($class->associationMappings as $assoc)
      {
        if ($assoc->isOwningSide) {
          $targetClass = $em->getClassMetadata($assoc->targetEntityName);

          if ( ! $calc->hasClass($targetClass->name)) {
              $calc->addClass($targetClass);
          }

          // add dependency ($targetClass before $class)
          $calc->addDependency($targetClass, $class);
        }
      }
    }

    return $calc->getCommitOrder();
  }
}
