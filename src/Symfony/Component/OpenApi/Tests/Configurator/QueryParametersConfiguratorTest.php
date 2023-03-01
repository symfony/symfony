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

use Symfony\Component\OpenApi\Builder\OpenApiBuilder;
use Symfony\Component\OpenApi\Configurator\QueryParametersConfigurator;
use Symfony\Component\OpenApi\Configurator\ReferenceConfigurator;
use Symfony\Component\OpenApi\Model\Parameter;
use Symfony\Component\OpenApi\Model\ParameterIn;
use Symfony\Component\OpenApi\Tests\Configurator\fixtures\ClassWithoutQueryParameterDescribeMethod;
use Symfony\Component\OpenApi\Tests\Configurator\fixtures\ClassWithQueryParameterDescribeMethod;

class QueryParametersConfiguratorTest extends AbstractConfiguratorTestCase
{
    public function testEmpty(): void
    {
        $configurator = new QueryParametersConfigurator(new OpenApiBuilder());

        $this->assertEmpty($configurator->getParameters());
    }

    public function testAddQueryParameters(): void
    {
        $configurator = new QueryParametersConfigurator(new OpenApiBuilder());

        $configurator
            ->queryParameter('test1')
            ->queryParameter('test2')
        ;

        $parameters = $configurator->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertSame('#/components/parameters/test1', $parameters[0]->getRef());
        $this->assertSame('#/components/parameters/test2', $parameters[1]->getRef());
    }

    public function testFromDefinitionEmpty(): void
    {
        $configurator = QueryParametersConfigurator::createFromDefinition('', new OpenApiBuilder());

        $this->assertInstanceOf(QueryParametersConfigurator::class, $configurator);
        $this->assertEmpty($configurator->getParameters());
    }

    public function testFromDefinitionQueryParameter(): void
    {
        $definition = new QueryParametersConfigurator(new OpenApiBuilder());
        $configurator = QueryParametersConfigurator::createFromDefinition($definition, new OpenApiBuilder());

        $this->assertSame($configurator, $definition);
    }

    public function testFromDefinitionReference(): void
    {
        $definition = new ReferenceConfigurator('test');
        $configurator = QueryParametersConfigurator::createFromDefinition($definition, new OpenApiBuilder());

        $this->assertSame($configurator, $definition);
    }

    public function testFromDefinitionClassWithoutDescribe(): void
    {
        /** @var ReferenceConfigurator $configurator */
        $configurator = QueryParametersConfigurator::createFromDefinition(ClassWithoutQueryParameterDescribeMethod::class, new OpenApiBuilder());

        $this->assertInstanceOf(ReferenceConfigurator::class, $configurator);
        $this->assertSame(
            '#/components/parameters/Symfony_Component_OpenApi_Tests_Configurator_fixtures_ClassWithoutQueryParameterDescribeMethod',
            $configurator->build()->getRef()
        );
    }

    public function testFromDefinitionClassWithDescribe(): void
    {
        /** @var QueryParametersConfigurator $configurator */
        $configurator = QueryParametersConfigurator::createFromDefinition(ClassWithQueryParameterDescribeMethod::class, new OpenApiBuilder());

        $this->assertInstanceOf(QueryParametersConfigurator::class, $configurator);

        $parameters = $configurator->getParameters();

        $this->assertCount(1, $parameters);

        /** @var Parameter $parameter */
        $parameter = $parameters[0];

        $this->assertInstanceOf(Parameter::class, $parameter);
        $this->assertSame('test', $parameter->getName());
        $this->assertSame(ParameterIn::QUERY, $parameter->getIn());
    }
}
