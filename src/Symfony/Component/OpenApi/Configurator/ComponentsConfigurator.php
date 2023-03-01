<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Configurator;

use Symfony\Component\OpenApi\Model\Components;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class ComponentsConfigurator
{
    use Traits\ExtensionsTrait;

    private array $schemas = [];
    private array $responses = [];
    private array $parameters = [];
    private array $examples = [];
    private array $requestBodies = [];
    private array $securitySchemes = [];
    private array $links = [];
    private array $callbacks = [];
    private array $pathItems = [];

    public function build(Components $toMergeWith = null): Components
    {
        if (!$toMergeWith) {
            $toMergeWith = new Components();
        }

        return new Components(
            array_merge($toMergeWith->getSchemas() ?: [], $this->schemas) ?: null,
            array_merge($toMergeWith->getResponses() ?: [], $this->responses) ?: null,
            array_merge($toMergeWith->getParameters() ?: [], $this->parameters) ?: null,
            array_merge($toMergeWith->getExamples() ?: [], $this->examples) ?: null,
            array_merge($toMergeWith->getRequestBodies() ?: [], $this->requestBodies) ?: null,
            array_merge($toMergeWith->getSecuritySchemes() ?: [], $this->securitySchemes) ?: null,
            array_merge($toMergeWith->getLinks() ?: [], $this->links) ?: null,
            array_merge($toMergeWith->getCallbacks() ?: [], $this->callbacks) ?: null,
            array_merge($toMergeWith->getPathItems() ?: [], $this->pathItems) ?: null,
            array_merge($toMergeWith->getSpecificationExtensions() ?: [], $this->specificationExtensions) ?: [],
        );
    }

    /**
     * @return $this
     */
    public function schema(string $name, SchemaConfigurator|string $schema): static
    {
        $this->schemas[ReferenceConfigurator::normalize($name)] = SchemaConfigurator::createFromDefinition($schema)->build();

        return $this;
    }

    public function response(string $name, ResponseConfigurator $response): static
    {
        $this->responses[ReferenceConfigurator::normalize($name)] = $response->build();

        return $this;
    }

    public function parameter(string $name, ParameterConfigurator $parameter): static
    {
        $this->parameters[ReferenceConfigurator::normalize($name)] = $parameter->build();

        return $this;
    }

    public function example(string $name, ExampleConfigurator $example): static
    {
        $this->examples[ReferenceConfigurator::normalize($name)] = $example->build();

        return $this;
    }

    public function requestBody(string $name, RequestBodyConfigurator $requestBody): static
    {
        $this->requestBodies[ReferenceConfigurator::normalize($name)] = $requestBody->build();

        return $this;
    }

    public function securityScheme(string $name, SecuritySchemeConfigurator $securityScheme): static
    {
        $this->securitySchemes[ReferenceConfigurator::normalize($name)] = $securityScheme->build();

        return $this;
    }

    public function callback(string $name, CallbackRequestConfigurator $callback): static
    {
        $this->callbacks[ReferenceConfigurator::normalize($name)] = $callback->build();

        return $this;
    }

    public function link(string $name, LinkConfigurator $link): static
    {
        $this->links[ReferenceConfigurator::normalize($name)] = $link->build();

        return $this;
    }

    public function pathItem(string $name, PathItemConfigurator $pathItem): static
    {
        $reference = ReferenceConfigurator::normalize($name);
        $this->pathItems[$reference] = $pathItem->build($this->pathItems[$reference] ?? null);

        return $this;
    }
}
