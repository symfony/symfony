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
interface OpenApiBuilderInterface
{
    public function schema(SchemaConfigurator|ReferenceConfigurator|string $definition = null): SchemaConfigurator|ReferenceConfigurator;

    public function callbackRequest(): CallbackRequestConfigurator;

    public function components(): ComponentsConfigurator;

    public function content(): MediaTypeConfigurator;

    public function encoding(): EncodingConfigurator;

    public function example(): ExampleConfigurator;

    public function info(): InfoConfigurator;

    public function link(): LinkConfigurator;

    public function mediaType(): MediaTypeConfigurator;

    public function operation(): OperationConfigurator;

    public function parameter(string $name): ParameterConfigurator;

    public function queryParameter(string $name): QueryParameterConfigurator;

    public function queryParameters(): QueryParametersConfigurator;

    public function pathItem(): PathItemConfigurator;

    public function reference(string $ref): ReferenceConfigurator;

    public function requestBody(): RequestBodyConfigurator;

    public function response(): ResponseConfigurator;

    public function responses(): ResponsesConfigurator;

    public function securityScheme(): SecuritySchemeConfigurator;

    public function server(string $url): ServerConfigurator;

    public function tag(): TagConfigurator;
}
