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

use Symfony\Component\OpenApi\Configurator\CallbackRequestConfigurator;
use Symfony\Component\OpenApi\Configurator\ComponentsConfigurator;
use Symfony\Component\OpenApi\Configurator\ExampleConfigurator;
use Symfony\Component\OpenApi\Configurator\LinkConfigurator;
use Symfony\Component\OpenApi\Configurator\ParameterConfigurator;
use Symfony\Component\OpenApi\Configurator\PathItemConfigurator;
use Symfony\Component\OpenApi\Configurator\RequestBodyConfigurator;
use Symfony\Component\OpenApi\Configurator\ResponseConfigurator;
use Symfony\Component\OpenApi\Configurator\SchemaConfigurator;
use Symfony\Component\OpenApi\Configurator\SecuritySchemeConfigurator;
use Symfony\Component\OpenApi\Model\CallbackRequest;
use Symfony\Component\OpenApi\Model\Components;
use Symfony\Component\OpenApi\Model\Example;
use Symfony\Component\OpenApi\Model\Link;
use Symfony\Component\OpenApi\Model\Parameter;
use Symfony\Component\OpenApi\Model\PathItem;
use Symfony\Component\OpenApi\Model\RequestBody;
use Symfony\Component\OpenApi\Model\Response;
use Symfony\Component\OpenApi\Model\Schema;
use Symfony\Component\OpenApi\Model\SecurityScheme;

class ComponentsConfiguratorTest extends AbstractConfiguratorTestCase
{
    public function testBuildEmpty(): void
    {
        $components = (new ComponentsConfigurator())->build();
        $this->assertInstanceOf(Components::class, $components);
        $this->assertNull($components->getSchemas());
        $this->assertNull($components->getResponses());
        $this->assertNull($components->getParameters());
        $this->assertNull($components->getExamples());
        $this->assertNull($components->getRequestBodies());
        $this->assertNull($components->getSecuritySchemes());
        $this->assertNull($components->getLinks());
        $this->assertNull($components->getCallbacks());
        $this->assertNull($components->getPathItems());
        $this->assertSame([], $components->getSpecificationExtensions());
    }

    public function testBuildFull(): void
    {
        [$schemaConfigurator, $schema] = $this->createConfiguratorMock(SchemaConfigurator::class, Schema::class);
        [$responseConfigurator, $response] = $this->createConfiguratorMock(ResponseConfigurator::class, Response::class);
        [$parameterConfigurator, $parameter] = $this->createConfiguratorMock(ParameterConfigurator::class, Parameter::class);
        [$exampleConfigurator, $example] = $this->createConfiguratorMock(ExampleConfigurator::class, Example::class);
        [$requestBodyConfigurator, $requestBody] = $this->createConfiguratorMock(RequestBodyConfigurator::class, RequestBody::class);
        [$securitySchemeConfigurator, $securityScheme] = $this->createConfiguratorMock(SecuritySchemeConfigurator::class, SecurityScheme::class);
        [$linkConfigurator, $link] = $this->createConfiguratorMock(LinkConfigurator::class, Link::class);
        [$callbackRequestConfigurator, $callbackRequest] = $this->createConfiguratorMock(CallbackRequestConfigurator::class, CallbackRequest::class);
        [$pathItemConfigurator, $pathItem] = $this->createConfiguratorMock(PathItemConfigurator::class, PathItem::class);

        $configurator = (new ComponentsConfigurator())
            ->schema('SchemaName', $schemaConfigurator)
            ->response('ResponseName', $responseConfigurator)
            ->parameter('ParameterName', $parameterConfigurator)
            ->example('ExampleName', $exampleConfigurator)
            ->requestBody('RequestBodyName', $requestBodyConfigurator)
            ->securityScheme('SecuritySchemeName', $securitySchemeConfigurator)
            ->link('LinkName', $linkConfigurator)
            ->callback('CallbackRequestName', $callbackRequestConfigurator)
            ->pathItem('PathItemName', $pathItemConfigurator)
            ->specificationExtension('x-ext', 'value')
        ;

        $components = $configurator->build();
        $this->assertInstanceOf(Components::class, $components);
        $this->assertSame($schema, $components->getSchemas()['SchemaName']);
        $this->assertSame($response, $components->getResponses()['ResponseName']);
        $this->assertSame($parameter, $components->getParameters()['ParameterName']);
        $this->assertSame($example, $components->getExamples()['ExampleName']);
        $this->assertSame($requestBody, $components->getRequestBodies()['RequestBodyName']);
        $this->assertSame($securityScheme, $components->getSecuritySchemes()['SecuritySchemeName']);
        $this->assertSame($link, $components->getLinks()['LinkName']);
        $this->assertSame($callbackRequest, $components->getCallbacks()['CallbackRequestName']);
        $this->assertSame($pathItem, $components->getPathItems()['PathItemName']);
        $this->assertSame(['x-ext' => 'value'], $components->getSpecificationExtensions());
    }
}
