<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\EventListener;

@trigger_error(sprintf('Class "%s" is deprecated since Symfony 3.4 and will be removed in 4.0. Use Symfony\Bundle\AclBundle\EventListener\AclSchemaListener instead.', AclSchemaListener::class), E_USER_DEPRECATED);

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Symfony\Component\Security\Acl\Dbal\Schema;

/**
 * Merges ACL schema into the given schema.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @deprecated since 3.4, to be removed in 4.0
 */
class AclSchemaListener
{
    private $schema;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $args)
    {
        $schema = $args->getSchema();
        $this->schema->addToSchema($schema);
    }
}
