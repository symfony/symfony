<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Builder;

use Symfony\Component\OpenApi\Configurator\CallbackRequestConfigurator;
use Symfony\Component\OpenApi\Configurator\ComponentsConfigurator;
use Symfony\Component\OpenApi\Configurator\EncodingConfigurator;
use Symfony\Component\OpenApi\Configurator\ExampleConfigurator;
use Symfony\Component\OpenApi\Configurator\InfoConfigurator;
use Symfony\Component\OpenApi\Configurator\LinkConfigurator;
use Symfony\Component\OpenApi\Configurator\MediaTypeConfigurator;
use Symfony\Component\OpenApi\Configurator\OperationConfigurator;
use Symfony\Component\OpenApi\Configurator\ParameterConfigurator;
use Symfony\Component\OpenApi\Configurator\PathItemConfigurator;
use Symfony\Component\OpenApi\Configurator\QueryParameterConfigurator;
use Symfony\Component\OpenApi\Configurator\QueryParametersConfigurator;
use Symfony\Component\OpenApi\Configurator\ReferenceConfigurator;
use Symfony\Component\OpenApi\Configurator\RequestBodyConfigurator;
use Symfony\Component\OpenApi\Configurator\ResponseConfigurator;
use Symfony\Component\OpenApi\Configurator\ResponsesConfigurator;
use Symfony\Component\OpenApi\Configurator\SchemaConfigurator;
use Symfony\Component\OpenApi\Configurator\SecuritySchemeConfigurator;
use Symfony\Component\OpenApi\Configurator\ServerConfigurator;
use Symfony\Component\OpenApi\Configurator\TagConfigurator;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class OpenApiBuilder implements OpenApiBuilderInterface
{
    public function schema(SchemaConfigurator|ReferenceConfigurator|string $definition = null): SchemaConfigurator|ReferenceConfigurator
    {
        return SchemaConfigurator::createFromDefinition($definition);
    }

    public function callbackRequest(): CallbackRequestConfigurator
    {
        return new CallbackRequestConfigurator();
    }

    public function components(): ComponentsConfigurator
    {
        return new ComponentsConfigurator();
    }

    public function content(): MediaTypeConfigurator
    {
        return new MediaTypeConfigurator();
    }

    public function encoding(): EncodingConfigurator
    {
        return new EncodingConfigurator();
    }

    public function example(): ExampleConfigurator
    {
        return new ExampleConfigurator();
    }

    public function info(): InfoConfigurator
    {
        return new InfoConfigurator();
    }

    public function link(): LinkConfigurator
    {
        return new LinkConfigurator();
    }

    public function mediaType(): MediaTypeConfigurator
    {
        return new MediaTypeConfigurator();
    }

    public function operation(): OperationConfigurator
    {
        return new OperationConfigurator($this);
    }

    public function parameter(string $name): ParameterConfigurator
    {
        return new ParameterConfigurator($name);
    }

    public function queryParameter(string $name): QueryParameterConfigurator
    {
        return new QueryParameterConfigurator($name);
    }

    public function queryParameters(): QueryParametersConfigurator
    {
        return new QueryParametersConfigurator($this);
    }

    public function pathItem(): PathItemConfigurator
    {
        return new PathItemConfigurator($this);
    }

    public function reference(string $ref): ReferenceConfigurator
    {
        return new ReferenceConfigurator($ref);
    }

    public function requestBody(): RequestBodyConfigurator
    {
        return new RequestBodyConfigurator();
    }

    public function response(): ResponseConfigurator
    {
        return new ResponseConfigurator();
    }

    public function responses(): ResponsesConfigurator
    {
        return new ResponsesConfigurator();
    }

    public function securityScheme(): SecuritySchemeConfigurator
    {
        return new SecuritySchemeConfigurator();
    }

    public function server(string $url): ServerConfigurator
    {
        return new ServerConfigurator($url);
    }

    public function tag(): TagConfigurator
    {
        return new TagConfigurator();
    }
}
