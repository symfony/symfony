<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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
use Symfony\Bundle\FrameworkBundle\Util\Filesystem;
use Doctrine\Common\Cli\Configuration;
use Doctrine\Common\Cli\CliController as DoctrineCliController;
use Doctrine\DBAL\Connection;

/**
 * Database tool allows you to easily drop and create your configured databases.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class DropDatabaseDoctrineCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:database:drop')
            ->setDescription('Drop the configured databases.')
            ->addOption('connection', null, InputOption::VALUE_OPTIONAL, 'The connection to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:database:drop</info> command drops the default connections database:

  <info>./app/console doctrine:database:drop</info>

You can also optionally specify the name of a connection to drop the database for:

  <info>./app/console doctrine:database:drop --connection=default</info>
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
            $this->dropDatabaseForConnection($connection, $output);
            $found = true;
        }
        if (false === $found) {
            if ($input->getOption('connection')) {
                throw new \InvalidArgumentException(sprintf('<error>Could not find a connection named <comment>%s</comment></error>', $input->getOption('connection')));
            } else {
                throw new \InvalidArgumentException(sprintf('<error>Could not find any configured connections</error>', $input->getOption('connection')));
            }
        }
    }

    protected function dropDatabaseForConnection(Connection $connection, OutputInterface $output)
    {
        $params = $connection->getParams();
        $name = isset($params['path']) ? $params['path']:$params['dbname'];

        try {
            $connection->getSchemaManager()->dropDatabase($name);
            $output->writeln(sprintf('<info>Dropped database for connection named <comment>%s</comment></info>', $name));
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Could not drop database for connection named <comment>%s</comment></error>', $name));
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
        }
    }
}