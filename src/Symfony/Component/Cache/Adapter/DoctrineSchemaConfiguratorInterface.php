<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;

/**
 * @internal
 */
interface DoctrineSchemaConfiguratorInterface
{
    /**
     * Adds the Table to the Schema if the adapter uses this Connection.
     */
    public function configureSchema(Schema $schema, Connection $forConnection): void;
}
