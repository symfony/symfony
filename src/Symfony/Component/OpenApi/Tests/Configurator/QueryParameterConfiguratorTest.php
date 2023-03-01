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

use Symfony\Component\OpenApi\Configurator\ExampleConfigurator;
use Symfony\Component\OpenApi\Configurator\MediaTypeConfigurator;
use Symfony\Component\OpenApi\Configurator\QueryParameterConfigurator;
use Symfony\Component\OpenApi\Configurator\SchemaConfigurator;
use Symfony\Component\OpenApi\Model\Example;
use Symfony\Component\OpenApi\Model\MediaType;
use Symfony\Component\OpenApi\Model\Parameter;
use Symfony\Component\OpenApi\Model\ParameterIn;
use Symfony\Component\OpenApi\Model\Schema;

class QueryParameterConfiguratorTest extends AbstractConfiguratorTestCase
{
    public function testBuildEmpty(): void
    {
        $configurator = new QueryParameterConfigurator('name');

        $parameter = $configurator->build();
        $this->assertInstanceOf(Parameter::class, $parameter);
        $this->assertSame('name', $parameter->getName());
        $this->assertSame(ParameterIn::QUERY, $parameter->getIn());
        $this->assertNull($parameter->getDescription());
        $this->assertNull($parameter->getRequired());
        $this->assertNull($parameter->getDeprecated());
        $this->assertNull($parameter->getAllowEmptyValue());
        $this->assertNull($parameter->getStyle());
        $this->assertNull($parameter->getExplode());
        $this->assertNull($parameter->getAllowReserved());
        $this->assertNull($parameter->getSchema());
        $this->assertNull($parameter->getExample());
        $this->assertNull($parameter->getExamples());
        $this->assertNull($parameter->getContent());
        $this->assertSame([], $parameter->getSpecificationExtensions());
    }

    public function testBuildFull(): void
    {
        [$schemaConfigurator, $schema] = $this->createConfiguratorMock(SchemaConfigurator::class, Schema::class);
        [$exampleConfigurator, $example] = $this->createConfiguratorMock(ExampleConfigurator::class, Example::class);
        [$mediaTypeConfigurator, $mediaType] = $this->createConfiguratorMock(MediaTypeConfigurator::class, MediaType::class);

        $configurator = (new QueryParameterConfigurator('name'))
            ->name('renamed')
            ->description('description')
            ->required(true)
            ->deprecated(true)
            ->allowEmptyValue(true)
            ->style('style')
            ->explode(true)
            ->allowReserved(true)
            ->schema($schemaConfigurator)
            ->example('example')
            ->example('ExampleName', $exampleConfigurator)
            ->content('application/json', $mediaTypeConfigurator)
            ->specificationExtension('x-ext', 'value')
        ;

        $parameter = $configurator->build();
        $this->assertInstanceOf(Parameter::class, $parameter);
        $this->assertSame('renamed', $parameter->getName());
        $this->assertSame(ParameterIn::QUERY, $parameter->getIn());
        $this->assertSame('description', $parameter->getDescription());
        $this->assertTrue($parameter->getRequired());
        $this->assertTrue($parameter->getDeprecated());
        $this->assertTrue($parameter->getAllowEmptyValue());
        $this->assertSame('style', $parameter->getStyle());
        $this->assertTrue($parameter->getExplode());
        $this->assertTrue($parameter->getAllowReserved());
        $this->assertSame($schema, $parameter->getSchema());
        $this->assertSame('example', $parameter->getExample());
        $this->assertSame($example, $parameter->getExamples()['ExampleName']);
        $this->assertSame($mediaType, $parameter->getContent()['application/json']);
        $this->assertSame(['x-ext' => 'value'], $parameter->getSpecificationExtensions());
    }
}
