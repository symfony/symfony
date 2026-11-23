<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\JsonSchema\Configuration;
use Symfony\Component\JsonSchema\ParameterSchemaEnricher\ParameterSchema;
use Symfony\Component\JsonSchema\ParameterSchemaEnricher\ParameterSchemaEnricherInterface;
use Symfony\Component\JsonSchema\SchemaDocument;
use Symfony\Component\TypeInfo\Type;

class TypeRecordingParameterSchemaEnricher implements ParameterSchemaEnricherInterface
{
    /**
     * @var array<string, ?Type>
     */
    public array $types = [];

    public function enrich(ParameterSchema $parameter, Configuration $config, SchemaDocument $document): ParameterSchema
    {
        $this->types[$parameter->parameter->name] = $parameter->type;

        return $parameter;
    }
}
