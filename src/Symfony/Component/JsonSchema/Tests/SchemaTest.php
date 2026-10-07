<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonSchema\Dialect;
use Symfony\Component\JsonSchema\Schema;

class SchemaTest extends TestCase
{
    public function testJsonSchema202012PlacesDefinitionsUnderDefs()
    {
        $schema = new Schema(['$ref' => '#/$defs/Foo'], ['Foo' => ['type' => 'object']], Dialect::jsonSchema202012());

        $this->assertSame([
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$ref' => '#/$defs/Foo',
            '$defs' => ['Foo' => ['type' => 'object']],
        ], $schema->toArray());
    }

    public function testOpenApiPlacesDefinitionsUnderComponentsSchemas()
    {
        $schema = new Schema(['$ref' => '#/components/schemas/Foo'], ['Foo' => ['type' => 'object']], Dialect::openApi31());

        $this->assertSame([
            '$ref' => '#/components/schemas/Foo',
            'components' => ['schemas' => ['Foo' => ['type' => 'object']]],
        ], $schema->toArray());
    }

    public function testSwaggerPlacesDefinitionsUnderDefinitions()
    {
        $schema = new Schema(['$ref' => '#/definitions/Foo'], ['Foo' => ['type' => 'object']], Dialect::swagger20());

        $this->assertSame([
            '$ref' => '#/definitions/Foo',
            'definitions' => ['Foo' => ['type' => 'object']],
        ], $schema->toArray());
    }

    public function testToArrayOmitsEmptyDefinitions()
    {
        $schema = new Schema(['type' => 'string'], [], Dialect::openApi31());

        $this->assertSame(['type' => 'string'], $schema->toArray());
    }

    public function testFlattenInlinesEveryAcyclicReference()
    {
        $schema = new Schema(
            ['type' => 'array', 'items' => ['$ref' => '#/$defs/Book']],
            [
                'Book' => ['type' => 'object', 'properties' => ['author' => ['$ref' => '#/$defs/Author']]],
                'Author' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
            ],
            Dialect::jsonSchema202012(),
        );

        $flattened = $schema->flatten();

        $this->assertSame([], $flattened->getDefinitions());
        $this->assertSame([
            'type' => 'array',
            'items' => ['type' => 'object', 'properties' => ['author' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]]]],
        ], $flattened->getRoot());
    }

    public function testFlattenKeepsReferencesThatCloseACycle()
    {
        $schema = new Schema(
            ['$ref' => '#/$defs/Employee'],
            [
                'Employee' => ['type' => 'object', 'properties' => ['department' => ['$ref' => '#/$defs/Department']]],
                'Department' => ['type' => 'object', 'properties' => ['manager' => ['$ref' => '#/$defs/Employee']]],
            ],
            Dialect::jsonSchema202012(),
        );

        $flattened = $schema->flatten();

        $this->assertSame([
            'type' => 'object',
            'properties' => ['department' => ['type' => 'object', 'properties' => ['manager' => ['$ref' => '#/$defs/Employee']]]],
        ], $flattened->getRoot());
        $this->assertSame(['Employee'], array_keys($flattened->getDefinitions()));
        $this->assertSame(
            ['type' => 'object', 'properties' => ['department' => ['type' => 'object', 'properties' => ['manager' => ['$ref' => '#/$defs/Employee']]]]],
            $flattened->getDefinitions()['Employee'],
        );
    }

    public function testJsonSerializeMatchesToArray()
    {
        $schema = new Schema(['$ref' => '#/$defs/Foo'], ['Foo' => ['type' => 'object', 'properties' => ['any' => []]]], Dialect::jsonSchema202012());

        $this->assertSame(
            '{"$schema":"https:\/\/json-schema.org\/draft\/2020-12\/schema","$ref":"#\/$defs\/Foo","$defs":{"Foo":{"type":"object","properties":{"any":{}}}}}',
            json_encode($schema),
        );
    }
}
