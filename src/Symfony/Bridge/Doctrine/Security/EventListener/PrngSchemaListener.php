<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Security\EventListener;

use Symfony\Bridge\Doctrine\Security\PrngSchema;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

/**
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PrngSchemaListener
{
    private $schema;

    public function __construct(PrngSchema $schema)
    {
        $this->schema = $schema;
    }

    public function postGenerateSchema(GenerateSchemaEventArgs $args)
    {
        $this->schema->addToSchema($args->getSchema());
    }
}
