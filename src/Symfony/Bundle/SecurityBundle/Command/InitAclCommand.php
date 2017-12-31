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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Acl\Dbal\Schema;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\SchemaException;

@trigger_error(sprintf('Class "%s" is deprecated since Symfony 3.4 and will be removed in 4.0. Use Symfony\Bundle\AclBundle\Command\InitAclCommand instead.', InitAclCommand::class), E_USER_DEPRECATED);

/**
 * Installs the tables required by the ACL system.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @deprecated since version 3.4, to be removed in 4.0. See Symfony\Bundle\AclBundle\Command\SetAclCommand instead.
 */
class InitAclCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'init:acl';

    private $connection;
    private $schema;

    public function __construct($connection = null, Schema $schema = null)
    {
        if (!$connection instanceof Connection) {
            parent::__construct($connection);

            return;
        }

        parent::__construct();

        $this->connection = $connection;
        $this->schema = $schema;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        if (!$this->connection && !$this->getContainer()->has('security.acl.dbal.connection')) {
            return false;
        }

        return parent::isEnabled();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDescription('Mounts ACL tables in the database')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command mounts ACL tables in the database.

  <info>php %command.full_name%</info>

The name of the DBAL connection must be configured in your <info>app/config/security.yml</info> configuration file in the <info>security.acl.connection</info> variable.

  <info>security:
      acl:
          connection: default</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        (new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output))->warning('Command "init:acl" is deprecated since Symfony 3.4 and will be removed from SecurityBundle in 4.0. Install symfony/acl-bundle and use "acl:init" instead.');

        if (null === $this->connection) {
            $this->connection = $this->getContainer()->get('security.acl.dbal.connection');
            $this->schema = $this->getContainer()->get('security.acl.dbal.schema');
        }

        try {
            $this->schema->addToSchema($this->connection->getSchemaManager()->createSchema());
        } catch (SchemaException $e) {
            $output->writeln('Aborting: '.$e->getMessage());

            return 1;
        }

        foreach ($this->schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->connection->exec($sql);
        }

        $output->writeln('ACL tables have been initialized successfully.');
    }
}
