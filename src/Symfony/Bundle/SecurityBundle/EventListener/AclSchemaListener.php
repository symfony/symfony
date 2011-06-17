<?php

namespace Symfony\Bundle\SecurityBundle\EventListener;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

class AclSchemaListener
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $args)
    {
        $schema = $args->getSchema();
        $this->container->get('security.acl.dbal.schema')->addToSchema($schema);
    }
}