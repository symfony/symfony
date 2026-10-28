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
use Symfony\Component\JsonSchema\NullSyntax;
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

    public function testDefinitionsArePlacedUnderTheReferencePath()
    {
        $schema = new Schema(['$ref' => '#/definitions/Foo'], ['Foo' => ['type' => 'object']], new Dialect('#/definitions/', NullSyntax::Unsupported));

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

    public function testFlattenInlinesPropertiesNamedAfterKeywords()
    {
        $kind = ['type' => 'object', 'properties' => ['label' => ['type' => 'string']]];
        $properties = array_fill_keys(['type', 'enum', 'const', 'default', 'example', 'examples', 'required', 'properties', 'items'], ['$ref' => '#/$defs/Kind']);
        $schema = new Schema(['type' => 'object', 'properties' => $properties], ['Kind' => $kind], Dialect::jsonSchema202012());

        $flattened = $schema->flatten();

        $this->assertSame([], $flattened->getDefinitions());
        $this->assertSame(['type' => 'object', 'properties' => array_fill_keys(array_keys($properties), $kind)], $flattened->getRoot());
    }

    public function testFlattenKeepsTheRequiredPropertiesOfTheDefinitionAndOfTheReference()
    {
        $schema = new Schema(
            ['$ref' => '#/$defs/Circle', 'required' => ['kind']],
            ['Circle' => ['type' => 'object', 'properties' => ['kind' => ['const' => 'circle'], 'radius' => ['type' => 'number']], 'required' => ['radius']]],
            Dialect::jsonSchema202012(),
        );

        $this->assertSame(['radius', 'kind'], $schema->flatten()->getRoot()['required']);
    }

    public function testFlattenInlinesReferencesBesideAReference()
    {
        $schema = new Schema(
            ['$ref' => '#/$defs/Base', 'allOf' => [['$ref' => '#/$defs/WithId']]],
            ['Base' => ['type' => 'object'], 'WithId' => ['required' => ['id']]],
            Dialect::jsonSchema202012(),
        );

        $flattened = $schema->flatten();

        $this->assertSame(['type' => 'object', 'allOf' => [['required' => ['id']]]], $flattened->getRoot());
        $this->assertSame([], $flattened->getDefinitions());
    }

    public function testFlattenDoesNotTreatSiblingReferencesAsCycles()
    {
        $schema = new Schema(
            ['$ref' => '#/$defs/Base', 'properties' => ['child' => ['$ref' => '#/$defs/Base']]],
            ['Base' => ['type' => 'object']],
            Dialect::jsonSchema202012(),
        );

        $flattened = $schema->flatten();

        $this->assertSame(['type' => 'object', 'properties' => ['child' => ['type' => 'object']]], $flattened->getRoot());
        $this->assertSame([], $flattened->getDefinitions());
    }

    public function testFlattenInlinesReferencesBesideACyclicReference()
    {
        $schema = new Schema(
            ['$ref' => '#/$defs/Node'],
            [
                'Node' => ['type' => 'object', 'properties' => ['child' => ['$ref' => '#/$defs/Node', 'allOf' => [['$ref' => '#/$defs/WithId']]]]],
                'WithId' => ['required' => ['id']],
            ],
            Dialect::jsonSchema202012(),
        );

        $flattened = $schema->flatten();
        $expected = ['type' => 'object', 'properties' => ['child' => ['$ref' => '#/$defs/Node', 'allOf' => [['required' => ['id']]]]]];

        $this->assertSame($expected, $flattened->getRoot());
        $this->assertSame(['Node' => $expected], $flattened->getDefinitions());
    }

    public function testFlattenKeepsIntegerKeysOfKeywordsThatAreNotSchemas()
    {
        $schema = new Schema(['type' => 'object', 'dependentRequired' => ['2' => ['1']]], [], Dialect::jsonSchema202012());

        $this->assertSame(['type' => 'object', 'dependentRequired' => ['2' => ['1']]], $schema->flatten()->getRoot());
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

    public function testEmptySchemasAreEncodedAsObjectsByEveryAccessor()
    {
        $schema = new Schema(
            ['type' => 'object', 'properties' => ['any' => []], 'additionalProperties' => []],
            ['Foo' => ['type' => 'array', 'items' => []]],
            Dialect::openApi31(),
        );

        $this->assertSame('{"type":"object","properties":{"any":{}},"additionalProperties":{}}', json_encode($schema->getRoot()));
        $this->assertSame('{"Foo":{"type":"array","items":{}}}', json_encode($schema->getDefinitions()));
        $this->assertSame('{"type":"object","properties":{"any":{}},"additionalProperties":{},"components":{"schemas":{"Foo":{"type":"array","items":{}}}}}', json_encode($schema->toArray()));
        $this->assertSame('{}', json_encode((new Schema([], [], Dialect::openApi31()))->getRoot()));
    }
}
