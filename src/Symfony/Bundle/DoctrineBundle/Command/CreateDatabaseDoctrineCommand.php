<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\HttpKernel\Util\Filesystem;
use Doctrine\DBAL\Connection;

/**
 * Database tool allows you to easily drop and create your configured databases.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class CreateDatabaseDoctrineCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:database:create')
            ->setDescription('Create the configured databases.')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The connection to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:database:create</info> command creates the default connections database:

  <info>./app/console doctrine:database:create</info>

You can also optionally specify the name of a connection to create the database for:

  <info>./app/console doctrine:database:create --connection=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = $this->getDoctrineConnection($input->getOption('connection'));

        $params = $connection->getParams();
        $name = isset($params['path']) ? $params['path']:$params['dbname'];

        unset($params['dbname']);

        $tmpConnection = \Doctrine\DBAL\DriverManager::getConnection($params);

        try {
            $tmpConnection->getSchemaManager()->createDatabase($name);
            $output->writeln(sprintf('<info>Created database for connection named <comment>%s</comment></info>', $name));
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Could not create database for connection named <comment>%s</comment></error>', $name));
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
        }

        $tmpConnection->close();
    }
}