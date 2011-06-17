<?php

namespace Symfony\Bundle\SecurityBundle\EventListener;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Symfony\Component\Security\Core\Util\SecureRandomSchema;

class SecureRandomSchemaListener
{
    private $schema;

    public function __construct(SecureRandomSchema $schema)
    {
        $this->schema = $schema;
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $args)
    {
        $this->schema->addToSchema($args->getSchema());
    }
}