<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMigrationsBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Doctrine\DBAL\Migrations\Tools\Console\Command\GenerateCommand;
use Symfony\Bundle\FrameworkBundle\Command\Command;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bundle\DoctrineAbstractBundle\Common\DataFixtures\Loader as DataFixturesLoader;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Symfony\Bundle\DoctrineMigrationsBundle\SQLLogger\FixturesToMigrationSQLLogger;
use Symfony\Bundle\DoctrineMigrationsBundle\Command\DoctrineCommand;

/**
 * Command for generating a Doctrine database migration class from a set of fixtures.
 *
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class MigrationsGenerateFromDataFixturesCommand extends GenerateCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:migrations:generate:from-data-fixtures')
            ->addOption('fixtures', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The directory or file to load data fixtures from.')
            ->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command.')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ( ! class_exists('Doctrine\Common\DataFixtures\Loader')) {
            throw new \Exception('You must have the Doctrine data fixtures extension installed in order to use this command.');
        }

        $sqlLogger = new FixturesToMigrationSQLLogger();

        $container = $this->application->getKernel()->getContainer();

        $emName = $input->getOption('em');
        $emName = $emName ? $emName : 'default';

        DoctrineCommand::setApplicationEntityManager($this->application, $emName);

        $configuration = $this->getMigrationConfiguration($input, $output);
        DoctrineCommand::configureMigrations($this->application->getKernel()->getContainer(), $configuration);

        $emServiceName = sprintf('doctrine.orm.%s_entity_manager', $emName);
        $em = $container->get($emServiceName);

        $em->getConnection()->getConfiguration()->setSQLLogger($sqlLogger);

        $dirOrFile = $input->getOption('fixtures');
        if ($dirOrFile) {
            $paths = is_array($dirOrFile) ? $dirOrFile : array($dirOrFile);
        } else {
            $paths = array();
            foreach ($this->application->getKernel()->getBundles() as $bundle) {
                $paths[] = $bundle->getPath().'/DataFixtures/ORM';
            }
        }

        $loader = new DataFixturesLoader($container);
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $loader->loadFromDirectory($path);
            }
        }
        $fixtures = $loader->getFixtures();
        $purger = new ORMPurger($em);
        $executor = new ORMExecutor($em, $purger);
        $executor->setLogger(function($message) use ($output) {
            $output->writeln(sprintf('  <comment>></comment> <info>%s</info>', $message));
        });
        $executor->execute($fixtures);

        $queries = $sqlLogger->getQueries();
        foreach ($queries as $key => $query) {
            foreach ($query[1] as $key2 => $param) {
                if (is_object($param)) {
                    if ($param instanceOf \DateTime) {
                        $queries[$key][1][$key2] = $param->format('Y-m-d\TH:i:s\Z');
                    } else if (in_array('__toString', get_class_methods($param))) {
                        $queries[$key][1][$key2] = (string)$param;
                    } else {
                        $output->writeln(sprintf('  <comment>></comment> <info>cannot convert object of type %s to a string</info>', get_class($param)));
                    }
                }
            }
        }

        $output->writeln(sprintf('  <comment>></comment> <info>%s queries logged</info>', count($queries)));
        foreach ($queries as $query) {
            $output->writeln(sprintf('    <comment>-</comment> <info>%s (parameters? %s)</info>', $query[0], is_array($query[1]) ? 'yes' : 'no'));
        }

        $version = date('YmdHis');

        $up = $this->buildCodeFromSql($configuration, $queries);
        $down = 'throw new \Doctrine\DBAL\Migrations\IrreversibleMigrationException();';
        $path = $this->generateMigration($configuration, $input, $version, $up, $down);

        $output->writeln(sprintf('  <comment>></comment> <info>Generated new migration class to %s</info>', $path));
    }

    private function buildCodeFromSql(Configuration $configuration, array $queries)
    {
        $code = array();
        foreach ($queries as $query) {
            if (strpos($query[0], $configuration->getMigrationsTableName()) !== false) {
                continue;
            }
            $code[] = sprintf("\$this->addSql(\"%s\", %s);", $query[0], var_export($query[1], true));
        }
        return implode("\n", $code);
    }
}
