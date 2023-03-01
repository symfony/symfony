<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Configurator\Traits;

use Symfony\Component\OpenApi\Configurator\ReferenceConfigurator;
use Symfony\Component\OpenApi\Configurator\SchemaConfigurator;
use Symfony\Component\OpenApi\Model\Reference;
use Symfony\Component\OpenApi\Model\Schema;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
trait SchemaTrait
{
    private Schema|Reference|null $schema = null;

    public function schema(SchemaConfigurator|ReferenceConfigurator|string $schema): static
    {
        $this->schema = SchemaConfigurator::createFromDefinition($schema)->build();

        return $this;
    }
}
