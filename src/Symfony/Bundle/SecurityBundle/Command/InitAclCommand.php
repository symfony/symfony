<?php

namespace Symfony\Bundle\SecurityBundle\Command;

use Doctrine\DBAL\Schema\Comparator;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Bundle\FrameworkBundle\Command\Command;

/**
 * Installs the tables required by the ACL system
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class InitAclCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('init:acl');
        $this->addOption('dump-sql', null, InputOption::VALUE_NONE, 'Whether to dump the SQL statement');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Whether to execute the SQL statements');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $con = $this->container->get('security.acl.dbal.connection');
        $schema = $this->container->get('security.acl.dbal.schema');

        if ($input->getOption('force') === $input->getOption('dump-sql')) {
            throw new \InvalidArgumentException('This command needs to be run with one of these options: --force, or --dump-sql');
        }

        $execute = $input->getOption('force');
        $comparator = new Comparator();
        foreach ($comparator->compare($con->getSchemaManager()->createSchema(), $schema)->toSaveSql($con->getDatabasePlatform()) as $sql) {
            if ($execute) {
                $con->executeQuery($sql);
            } else {
                $output->writeln($sql);
            }
        }

        if ($execute) {
            $output->writeln('The database schema was updated successfully.');
        }
    }
}