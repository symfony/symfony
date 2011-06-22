<?php

namespace Symfony\Bundle\SecurityBundle\EventListener;

use Symfony\Component\Security\Acl\Dbal\Schema;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

class AclSchemaListener
{
    private $schema;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $args)
    {
        $this->schema->addToSchema($args->getSchema());
    }
}