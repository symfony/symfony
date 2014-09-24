<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Bridge\Doctrine\Security\SessionRegistry\Schema;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Installs the database schema required by the concurrent session Doctrine implementation
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class InitConcurrentSessionsCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('init:concurrent-session')
            ->setDescription('Executes the SQL needed to generate the database schema required by the concurrent sessions feature.')
            ->setHelp(<<<EOT
The <info>init:concurrent-session</info> command executes the SQL needed to
generate the database schema required by the concurrent session Doctrine implementation:

<info>./app/console init:concurrent-session</info>
EOT
        );
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = $this->getContainer()->get('security.session_registry.dbal.connection');
        $sm = $connection->getSchemaManager();
        $tableNames = $sm->listTableNames();
        $table = $this->getContainer()->getParameter('security.session_registry.dbal.session_information_table_name');

        if (in_array($table, $tableNames, true)) {
            $output->writeln(sprintf('The table "%s" already exists. Aborting.', $table));

            return;
        }

        $schema = new Schema($table);
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->exec($sql);
        }

        $output->writeln('concurrent session table have been initialized successfully.');
    }
}
