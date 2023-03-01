<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Tests\Configurator;

use Symfony\Component\OpenApi\Configurator\SchemaConfigurator;
use Symfony\Component\OpenApi\Model\Discriminator;
use Symfony\Component\OpenApi\Model\ExternalDocumentation;
use Symfony\Component\OpenApi\Model\Reference;
use Symfony\Component\OpenApi\Model\Schema;
use Symfony\Component\OpenApi\Model\Xml;

class SchemaConfiguratorTest extends AbstractConfiguratorTestCase
{
    public function testDefinitionEmpty(): void
    {
        $schema = SchemaConfigurator::createFromDefinition()->build();
        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertNull($schema->getType());
        $this->assertNull($schema->isNullable());
        $this->assertSame([], $schema->toArray());
    }

    public function testDefinitionNull(): void
    {
        $schema = SchemaConfigurator::createFromDefinition('null')->build();
        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertSame(['null'], $schema->getType());
        $this->assertTrue($schema->isNullable());
        $this->assertSame(['type' => ['null']], $schema->toArray());
    }

    public function testDefinitionTypeNullableOverriden(): void
    {
        $schema = SchemaConfigurator::createFromDefinition()->nullable(true)->type('string')->build();
        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertSame(['string'], $schema->getType());
        $this->assertFalse($schema->isNullable());
        $this->assertSame(['type' => ['string']], $schema->toArray());
    }

    public function testDefinitionTypeNullable(): void
    {
        $schema = SchemaConfigurator::createFromDefinition()->type('string')->nullable(true)->build();
        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertSame(['string', 'null'], $schema->getType());
        $this->assertTrue($schema->isNullable());
        $this->assertSame(['type' => ['string', 'null']], $schema->toArray());
    }

    public function testDefinitionFloat(): void
    {
        $schema = SchemaConfigurator::createFromDefinition('float')->build();
        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertSame(['number'], $schema->getType());
        $this->assertFalse($schema->isNullable());
        $this->assertSame(['type' => ['number']], $schema->toArray());
    }

    public function testDefinitionNullableString(): void
    {
        $schema = SchemaConfigurator::createFromDefinition('?string')->build();
        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertSame(['string', 'null'], $schema->getType());
        $this->assertTrue($schema->isNullable());
        $this->assertSame(['type' => ['string', 'null']], $schema->toArray());
    }

    public function testDefinitionRecursive(): void
    {
        $schema = SchemaConfigurator::createFromDefinition(SchemaConfigurator::createFromDefinition('?string'))->build();
        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertSame(['string', 'null'], $schema->getType());
        $this->assertTrue($schema->isNullable());
    }

    public function testDefinitionReference(): void
    {
        $reference = SchemaConfigurator::createFromDefinition('ReferenceName')->build();
        $this->assertInstanceOf(Reference::class, $reference);
        $this->assertSame('#/components/schemas/ReferenceName', $reference->getRef());
    }

    public function testDefinitionArray(): void
    {
        $schema = SchemaConfigurator::createFromDefinition(['float', 'null'])->build();
        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertSame(['number', 'null'], $schema->getType());
        $this->assertTrue($schema->isNullable());
    }

    public function testBuildEmpty(): void
    {
        $schema = (new SchemaConfigurator())->build();
        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertNull($schema->getTitle());
        $this->assertNull($schema->getDescription());
        $this->assertNull($schema->getMultipleOf());
        $this->assertNull($schema->getMaximum());
        $this->assertNull($schema->isExclusiveMaximum());
        $this->assertNull($schema->getMinimum());
        $this->assertNull($schema->isExclusiveMinimum());
        $this->assertNull($schema->getMaxLength());
        $this->assertNull($schema->getMinLength());
        $this->assertNull($schema->getPattern());
        $this->assertNull($schema->getMaxItems());
        $this->assertNull($schema->getMinItems());
        $this->assertNull($schema->isUniqueItems());
        $this->assertNull($schema->getMaxProperties());
        $this->assertNull($schema->getMinProperties());
        $this->assertNull($schema->getRequired());
        $this->assertNull($schema->getEnum());
        $this->assertNull($schema->getType());
        $this->assertNull($schema->getFormat());
        $this->assertNull($schema->getDefault());
        $this->assertNull($schema->isNullable());
        $this->assertNull($schema->isReadOnly());
        $this->assertNull($schema->isWriteOnly());
        $this->assertNull($schema->getExample());
        $this->assertNull($schema->isDeprecated());
        $this->assertSame([], $schema->getSpecificationExtensions());
        $this->assertNull($schema->getDiscriminator());
        $this->assertNull($schema->getXml());
        $this->assertNull($schema->getExternalDocs());
    }

    public function testBuildFull(): void
    {
        $configurator = (new SchemaConfigurator())
            ->title('title')
            ->description('description')
            ->multipleOf(2)
            ->maximum(100)
            ->exclusiveMaximum(true)
            ->minimum(10)
            ->exclusiveMinimum(true)
            ->maxLength(5)
            ->minLength(3)
            ->pattern('^[a-zA-Z]*$')
            ->maxItems(5)
            ->minItems(2)
            ->uniqueItems(true)
            ->maxProperties(3)
            ->minProperties(1)
            ->required(['foo', 'bar'])
            ->enum(['foo', 'bar'])
            ->type('?string')
            ->format('binary')
            ->default('foo')
            ->readOnly(true)
            ->writeOnly(true)
            ->example('foo')
            ->discriminator(propertyName: 'type', mapping: ['mapping' => 'value'], specificationExtensions: ['x-ext2' => 'value'])
            ->xml(name: 'name', namespace: 'namespace', prefix: 'prefix', attribute: true, wrapped: true, specificationExtensions: ['x-ext3' => 'value'])
            ->externalDocs(url: 'https://example.com', description: 'external docs', specificationExtensions: ['x-ext4' => 'value'])
            ->deprecated(true)
            ->specificationExtension('x-ext', 'value')
        ;

        $schema = $configurator->build();
        $this->assertInstanceOf(Schema::class, $schema);
        $this->assertSame('title', $schema->getTitle());
        $this->assertSame('description', $schema->getDescription());
        $this->assertSame(2, $schema->getMultipleOf());
        $this->assertSame(100, $schema->getMaximum());
        $this->assertTrue($schema->isExclusiveMaximum());
        $this->assertSame(10, $schema->getMinimum());
        $this->assertTrue($schema->isExclusiveMinimum());
        $this->assertSame(5, $schema->getMaxLength());
        $this->assertSame(3, $schema->getMinLength());
        $this->assertSame('^[a-zA-Z]*$', $schema->getPattern());
        $this->assertSame(5, $schema->getMaxItems());
        $this->assertSame(2, $schema->getMinItems());
        $this->assertTrue($schema->isUniqueItems());
        $this->assertSame(3, $schema->getMaxProperties());
        $this->assertSame(1, $schema->getMinProperties());
        $this->assertSame(['foo', 'bar'], $schema->getRequired());
        $this->assertSame(['foo', 'bar'], $schema->getEnum());
        $this->assertSame(['string', 'null'], $schema->getType());
        $this->assertSame('binary', $schema->getFormat());
        $this->assertSame('foo', $schema->getDefault());
        $this->assertTrue($schema->isNullable());
        $this->assertTrue($schema->isReadOnly());
        $this->assertTrue($schema->isWriteOnly());
        $this->assertSame('foo', $schema->getExample());
        $this->assertTrue($schema->isDeprecated());
        $this->assertSame(['x-ext' => 'value'], $schema->getSpecificationExtensions());
        $this->assertInstanceOf(Discriminator::class, $schema->getDiscriminator());
        $this->assertSame('type', $schema->getDiscriminator()->getPropertyName());
        $this->assertSame(['mapping' => 'value'], $schema->getDiscriminator()->getMapping());
        $this->assertSame(['x-ext2' => 'value'], $schema->getDiscriminator()->getSpecificationExtensions());
        $this->assertInstanceOf(Xml::class, $schema->getXml());
        $this->assertSame('name', $schema->getXml()->getName());
        $this->assertSame('namespace', $schema->getXml()->getNamespace());
        $this->assertSame('prefix', $schema->getXml()->getPrefix());
        $this->assertTrue($schema->getXml()->isAttribute());
        $this->assertTrue($schema->getXml()->isWrapped());
        $this->assertSame(['x-ext3' => 'value'], $schema->getXml()->getSpecificationExtensions());
        $this->assertInstanceOf(ExternalDocumentation::class, $schema->getExternalDocs());
        $this->assertSame('https://example.com', $schema->getExternalDocs()->getUrl());
        $this->assertSame('external docs', $schema->getExternalDocs()->getDescription());
        $this->assertSame(['x-ext4' => 'value'], $schema->getExternalDocs()->getSpecificationExtensions());
    }
}
