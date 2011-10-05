<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bridge\Doctrine\Schema\ProfilerSchema;

/**
 * Migrates the table required by the WebProfiler.
 *
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
class ProfilerUpdateCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('profiler:update')
            ->setDescription('Updates existing profiler table to new format')
            ->addOption('table', null, InputOption::VALUE_OPTIONAL, 'The table name for profiler data')
            ->setHelp(<<<EOT
The <info>profiler:update</info> command migrates profiler table in the database.

<info>php app/console profiler:update</info>
EOT
            );
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $connection = $this->getContainer()->get('doctrine')->getConnection();

        $table = $input->getOption('table');

        if (in_array($table, $connection->getSchemaManager()->listTableNames(), true)) {
            $output->writeln(sprintf('The table "%s" already exists. Aborting.', $table));

            return;
        }

        $schema = new ProfilerSchema($table);
        $oldSchema = $schema->createOldTable();
        foreach ($oldSchema->getMigrateToSql($schema->createNewTable(), $connection->getDatabasePlatform()) as $sql) {
            $connection->exec($sql);
        }

        $output->writeln('Profiler tables have been migrated successfully.');
    }
}