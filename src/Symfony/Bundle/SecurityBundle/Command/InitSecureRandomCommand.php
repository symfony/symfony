<?php

namespace Symfony\Bundle\SecurityBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Console\Input\InputOption;
use Doctrine\DBAL\Schema\Comparator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Initializes the CSPRNG fallback algorithm.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class InitSecureRandomCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('init:secure-random');
        $this->addArgument('phrase', InputArgument::REQUIRED, 'Whatever comes to your mind right now. You do not need to remember it, it does not need to be cryptic, or long, and it will not be stored in a decipherable way. One restriction however, you should not let this be generated in an automated fashion.');
        $this->addOption('dump-sql', null, InputOption::VALUE_NONE, 'Whether the SQL should be dumped.');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Whether the SQL should be executed.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $seed = base64_encode(hash('sha512', $input->getArgument('phrase'), true));

        if ($this->container->has('security.util.secure_random_seed_provider')) {
            $this->container->get('security.util.secure_random_seed_provider')->updateSeed($seed);

            $output->writeln('The CSPRNG has been initialized successfully.');
        } else if ($this->container->has('security.util.secure_random_connection')) {
            if ($input->getOption('force') === $input->getOption('dump-sql')) {
                throw new \InvalidArgumentException('This command needs to be run with one of these options: --force, or --dump-sql');
            }

            $con = $this->container->get('security.util.secure_random_connection');
            $schema = $this->container->get('security.util.secure_random_schema');

            $comparator = new Comparator();
            $execute = $input->getOption('force');
            foreach ($comparator->compare($con->getSchemaManager()->createSchema(), $schema)->toSaveSql($con->getDatabasePlatform()) as $sql) {
                if ($execute) {
                    $con->executeQuery($sql);
                } else {
                    $output->writeln($sql);
                }
            }

            $table = $this->container->getParameter('security.util.secure_random_table');
            $sql = $con->getDatabasePlatform()->getTruncateTableSQL($table);
            if ($execute) {
                $con->executeQuery($sql);
            } else {
                $output->writeln($sql);
            }

            $sql = "INSERT INTO {$table} VALUES (:seed, :updatedAt)";
            if ($execute) {
                $con->executeQuery($sql, array(
                    ':seed' => $seed,
                    ':updatedAt' => new \DateTime(),
                ), array(
                    ':updatedAt' => Type::DATETIME,
                ));
            } else {
                $output->writeln($sql);
            }

            if ($execute) {
                $output->writeln('The CSPRNG has been initialized successfully.');
            }
        } else {
            throw new \RuntimeException('No seed provider has been configured under path "security.util.secure_random".');
        }
    }
}