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

use Symfony\Component\OpenApi\Builder\OpenApiBuilderInterface;
use Symfony\Component\OpenApi\Model\Operation;
use Symfony\Component\OpenApi\Model\PathItem;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class PathItemConfigurator
{
    use Traits\DescriptionTrait;
    use Traits\ExtensionsTrait;
    use Traits\ParametersTrait;
    use Traits\QueryParametersTrait;
    use Traits\ServersTrait;
    use Traits\SummaryTrait;

    private ?string $ref = null;
    private ?Operation $get = null;
    private ?Operation $post = null;
    private ?Operation $put = null;
    private ?Operation $patch = null;
    private ?Operation $delete = null;
    private ?Operation $head = null;
    private ?Operation $options = null;
    private ?Operation $trace = null;

    public function __construct(OpenApiBuilderInterface $openApiBuilder)
    {
        $this->openApiBuilder = $openApiBuilder;
    }

    public function build(PathItem $toMergeWith = null): PathItem
    {
        return new PathItem(
            ref: $this->ref ?: $toMergeWith?->getRef(),
            summary: $this->summary ?: $toMergeWith?->getSummary(),
            description: $this->description ?: $toMergeWith?->getDescription(),
            get: $this->get ?: $toMergeWith?->getGet(),
            put: $this->put ?: $toMergeWith?->getPut(),
            post: $this->post ?: $toMergeWith?->getPost(),
            patch: $this->patch ?: $toMergeWith?->getPatch(),
            delete: $this->delete ?: $toMergeWith?->getDelete(),
            head: $this->head ?: $toMergeWith?->getHead(),
            options: $this->options ?: $toMergeWith?->getOptions(),
            trace: $this->trace ?: $toMergeWith?->getTrace(),
            servers: $this->servers ?: $toMergeWith?->getServers() ?: null,
            parameters: array_merge($this->parameters, $this->queryParameters) ?: $toMergeWith?->getParameters() ?: null,
            specificationExtensions: $this->specificationExtensions ?: $toMergeWith?->getSpecificationExtensions() ?: [],
        );
    }

    public function ref(string $ref): static
    {
        $this->ref = $ref;

        return $this;
    }

    public function get(OperationConfigurator $get): static
    {
        $this->get = $get->build();

        return $this;
    }

    public function post(OperationConfigurator $post): static
    {
        $this->post = $post->build();

        return $this;
    }

    public function put(OperationConfigurator $put): static
    {
        $this->put = $put->build();

        return $this;
    }

    public function patch(OperationConfigurator $patch): static
    {
        $this->patch = $patch->build();

        return $this;
    }

    public function delete(OperationConfigurator $delete): static
    {
        $this->delete = $delete->build();

        return $this;
    }

    public function head(OperationConfigurator $head): static
    {
        $this->head = $head->build();

        return $this;
    }

    public function options(OperationConfigurator $options): static
    {
        $this->options = $options->build();

        return $this;
    }

    public function trace(OperationConfigurator $trace): static
    {
        $this->trace = $trace->build();

        return $this;
    }
}
