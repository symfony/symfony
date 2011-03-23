<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Set the indexes required by the MongoDB ACL provider
 *
 * @author Richard Shank <develop@zestic.com>
 */
class InitAclMongoDBCommand extends Command
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('init:acl:mongodb')
            ->addDescription('Set the indexes required by the MongoDB ACL provider')
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // todo: change services and paramters when the configuration has been finalized
        $mongo = $this->container->get('doctrine.odm.mongodb.default_connection');
        $this->dbName = $this->container->getParameter('doctrine.odm.mongodb.default_database');
        $db = $mongo->selectDatabase($this->dbName);

        $oidCollection = $db->selectCollection($this->container->getParameter('security.acl.dbal.oid_table_name'));
        $oidCollection->ensureIndex(array('randomKey' => 1), array());
        $oidCollection->ensureIndex(array('identifier' => 1, 'type' => 1));

        $entryCollection = $db->selectCollection($this->container->getParameter('security.acl.dbal.entry_table_name'));
        $entryCollection->ensureIndex(array('objectIdentity.$id' => 1));

        $output->writeln('ACL indexes have been initialized successfully.');
    }
}
