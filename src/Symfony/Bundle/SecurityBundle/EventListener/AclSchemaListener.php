<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\SecurityBundle\EventListener;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * Merges ACL schema into the given schema.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
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