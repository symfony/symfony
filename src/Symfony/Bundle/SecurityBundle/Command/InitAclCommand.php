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
use Symfony\Component\Security\Acl\Dbal\Schema;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Installs the tables required by the ACL system
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class InitAclCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('init:acl')
            ->setDescription('Mounts ACL tables in the database')
            ->setHelp(<<<EOT
The <info>init:acl</info> command mounts ACL tables in the database.

<info>php app/console init:acl</info>

The name of the DBAL connection must be configured in your <info>app/config/security.yml</info> configuration file in the <info>security.acl.connection</info> variable.

<info>security:
    acl:
        connection: default</info>
EOT
            );
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = $this->getContainer()->get('security.acl.dbal.connection');
        $sm = $connection->getSchemaManager();
        $tableNames = $sm->listTableNames();
        $tables = array(
            'class_table_name' => $this->getContainer()->getParameter('security.acl.dbal.class_table_name'),
            'sid_table_name'   => $this->getContainer()->getParameter('security.acl.dbal.sid_table_name'),
            'oid_table_name'   => $this->getContainer()->getParameter('security.acl.dbal.oid_table_name'),
            'oid_ancestors_table_name' => $this->getContainer()->getParameter('security.acl.dbal.oid_ancestors_table_name'),
            'entry_table_name' => $this->getContainer()->getParameter('security.acl.dbal.entry_table_name'),
        );

        foreach ($tables as $table) {
            if (in_array($table, $tableNames, true)) {
                $output->writeln(sprintf('The table "%s" already exists. Aborting.', $table));

                return;
            }
        }

        $schema = new Schema($tables);
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->exec($sql);
        }

        $output->writeln('ACL tables have been initialized successfully.');
    }
}
