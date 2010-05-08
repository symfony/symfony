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
use Doctrine\DBAL\Connection;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Database tool allows you to easily drop and create your configured databases.
 *
 * @package    Symfony
 * @subpackage Framework_DoctrineBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class CreateDatabaseDoctrineCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:database:create')
            ->setDescription('Create the configured databases.')
            ->addOption('connection', null, InputOption::PARAMETER_OPTIONAL, 'The connection to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:database:create</info> command creates the default connections database:

  <info>./symfony doctrine:database:create</info>

You can also optionally specify the name of a connection to create the database for:

  <info>./symfony doctrine:database:create --connection=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $found = false;
        $connections = $this->getDoctrineConnections();
        foreach ($connections as $name => $connection) {
            if ($input->getOption('connection') && $name != $input->getOption('connection')) {
                continue;
            }
            $this->createDatabaseForConnection($connection, $output);
            $found = true;
        }
        if ($found === false) {
            if ($input->getOption('connection')) {
                throw new \InvalidArgumentException(sprintf('<error>Could not find a connection named <comment>%s</comment></error>', $input->getOption('connection')));
            } else {
                throw new \InvalidArgumentException(sprintf('<error>Could not find any configured connections</error>', $input->getOption('connection')));
            }
        }
    }

    protected function createDatabaseForConnection(Connection $connection, OutputInterface $output)
    {
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