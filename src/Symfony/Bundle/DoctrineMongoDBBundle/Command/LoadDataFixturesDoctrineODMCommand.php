<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Util\Filesystem;
use Symfony\Bundle\DoctrineAbstractBundle\Common\DataFixtures\Loader as DataFixturesLoader;
use Doctrine\Common\DataFixtures\Executor\MongoDBExecutor;
use Doctrine\Common\DataFixtures\Purger\MongoDBPurger;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Internal\CommitOrderCalculator;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use InvalidArgumentException;

/**
 * Load data fixtures from bundles.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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

  <info>./app/console doctrine:mongodb:data:load</info>

You can also optionally specify the path to fixtures with the <info>--fixtures</info> option:

  <info>./app/console doctrine:mongodb:data:load --fixtures=/path/to/fixtures1 --fixtures=/path/to/fixtures2</info>

If you want to append the fixtures instead of flushing the database first you can use the <info>--append</info> option:

  <info>./app/console doctrine:mongodb:data:load --append</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dmName = $input->getOption('dm');
        $dmName = $dmName ? $dmName : 'default';
        $dmServiceName = sprintf('doctrine.odm.mongodb.%s_document_manager', $dmName);

        if (!$this->container->has($dmServiceName)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Could not find a document manager configured with the name "%s". Check your '.
                    'application configuration to configure your Doctrine document managers.', $dmName
                )
            );
        }

        $dm = $this->container->get($dmServiceName);
        $dirOrFile = $input->getOption('fixtures');
        if ($dirOrFile) {
            $paths = is_array($dirOrFile) ? $dirOrFile : array($dirOrFile);
        } else {
            $paths = array();
            foreach ($this->container->get('kernel')->getBundles() as $bundle) {
                $paths[] = $bundle->getPath().'/DataFixtures/MongoDB';
            }
        }

        $loader = new DataFixturesLoader($this->container);
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $loader->loadFromDirectory($path);
            }
        }

        $fixtures = $loader->getFixtures();
        if (!$fixtures) {
            throw new InvalidArgumentException(
                sprintf('Could not find any fixtures to load in: %s', "\n\n- ".implode("\n- ", $paths))
            );
        }

        $purger = new MongoDBPurger($dm);
        $executor = new MongoDBExecutor($dm, $purger);
        $executor->setLogger(function($message) use ($output) {
            $output->writeln(sprintf('  <comment>></comment> <info>%s</info>', $message));
        });
        $executor->execute($fixtures, $input->getOption('append'));
    }
}