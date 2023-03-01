<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Loader;

use Symfony\Component\OpenApi\Builder\OpenApiBuilderInterface;
use Symfony\Component\OpenApi\Configurator\SchemaConfigurator;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
interface SelfDescribingSchemaInterface
{
    public static function describeSchema(SchemaConfigurator $schema, OpenApiBuilderInterface $openApi): void;
}
