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
 * Manage the cache clearing of the Doctrine ORM.
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class ClearCacheDoctrineCommand extends DoctrineCommand
{
  /**
   * @see Command
   */
  protected function configure()
  {
    $this
      ->setName('doctrine:clear-cache')
      ->setDescription('Clear cache from configured query, result and metadata drivers.')
      ->setAliases(array('doctrine:cc'))
      ->addOption('query', null, null, 'Clear the query cache.')
      ->addOption('metadata', null, null, 'Clear the metadata cache.')
      ->addOption('result', null, null, 'Clear the result cache.')
      ->addOption('id', null, null, 'Clear a cache entry by its id.')
      ->addOption('regex', null, null, 'Clear cache entries that match a regular expression.')
      ->addOption('prefix', null, null, 'Clear cache entries that match a prefix.')
      ->addOption('suffix', null, null, 'Clear cache entries that match a suffix.')
    ;
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $options = $this->buildDoctrineCliTaskOptions($input, array(
      'query', 'metadata', 'result', 'id', 'regex', 'prefix', 'suffix'
    ));
    $this->runDoctrineCliTask('orm:clear-cache', $options);
  }
}