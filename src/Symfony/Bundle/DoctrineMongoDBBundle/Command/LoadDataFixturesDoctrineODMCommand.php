<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Finder\Finder;
use Symfony\Bundle\FrameworkBundle\Util\Filesystem;
use Doctrine\Common\Cli\Configuration;
use Doctrine\Common\Cli\CliController as DoctrineCliController;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Internal\CommitOrderCalculator;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Load data fixtures from bundles.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class LoadDataFixturesDoctrineODMCommand extends DoctrineODMCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:mongodb:data:load')
            ->setDescription('Load data fixtures to your database.')
            ->addOption('fixtures', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The directory or file to load data fixtures from.')
            ->addOption('append', null, InputOption::VALUE_OPTIONAL, 'Whether or not to append the data fixtures.', false)
            ->addOption('dm', null, InputOption::VALUE_REQUIRED, 'The document manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:mongodb:data:load</info> command loads data fixtures from your bundles:

  <info>./symfony doctrine:mongodb:data:load</info>

You can also optionally specify the path to fixtures with the <info>--fixtures</info> option:

  <info>./symfony doctrine:mongodb:data:load --fixtures=/path/to/fixtures1 --fixtures=/path/to/fixtures2</info>

If you want to append the fixtures instead of flushing the database first you can use the <info>--append</info> option:

  <info>./symfony doctrine:mongodb:data:load --append</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dmName = $input->getOption('dm');
        $dmName = $dmName ? $dmName : 'default';
        $dmServiceName = sprintf('doctrine.odm.mongodb.%s_document_manager', $dmName);
        $dm = $this->container->get($dmServiceName);
        $dirOrFile = $input->getOption('fixtures');
        if ($dirOrFile) {
            $paths = is_array($dirOrFile) ? $dirOrFile : array($dirOrFile);
        } else {
            $paths = array();
            $bundleDirs = $this->container->get('kernel')->getBundleDirs();
            foreach ($this->container->get('kernel')->getBundles() as $bundle) {
                $tmp = dirname(str_replace('\\', '/', get_class($bundle)));
                $namespace = str_replace('/', '\\', dirname($tmp));
                $class = basename($tmp);

                if (isset($bundleDirs[$namespace]) && is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/DataFixtures/MongoDB')) {
                    $paths[] = $dir;
                }
            }
        }

        $loader = new \Doctrine\Common\DataFixtures\Loader();
        foreach ($paths as $path) {
            $loader->loadFromDirectory($path);
        }
        $fixtures = $loader->getFixtures();
        $purger = new \Doctrine\Common\DataFixtures\Purger\MongoDBPurger($dm);
        $executor = new \Doctrine\Common\DataFixtures\Executor\MongoDBExecutor($dm, $purger);
        $executor->setLogger(function($message) use ($output) {
            $output->writeln(sprintf('  <comment>></comment> <info>%s</info>', $message));
        });
        $executor->execute($fixtures, $input->getOption('append'));
    }
}