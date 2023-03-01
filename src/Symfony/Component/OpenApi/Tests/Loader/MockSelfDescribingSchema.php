<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Tests\Loader;

use Symfony\Component\OpenApi\Builder\OpenApiBuilderInterface;
use Symfony\Component\OpenApi\Configurator\SchemaConfigurator;
use Symfony\Component\OpenApi\Loader\SelfDescribingSchemaInterface;

class MockSelfDescribingSchema implements SelfDescribingSchemaInterface
{
    public static function describeSchema(SchemaConfigurator $schema, OpenApiBuilderInterface $openApi): void
    {
        $schema
            ->title('Mock')
            ->property('username', 'string')
            ->property('status', $openApi->schema()->enum(['active', 'banned']))
        ;
    }
}
