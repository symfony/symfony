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
use Symfony\Component\OpenApi\Model\OpenApiTrait;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class QueryParametersConfigurator
{
    use OpenApiTrait;
    use Traits\ExtensionsTrait;
    use Traits\QueryParametersTrait;

    public function __construct(OpenApiBuilderInterface $openApiBuilder)
    {
        $this->openApiBuilder = $openApiBuilder;
    }

    public static function createFromDefinition(self|ReferenceConfigurator|string $definition, OpenApiBuilderInterface $openApiBuilder): self|ReferenceConfigurator
    {
        // Empty schema
        if (!$definition) {
            return new self($openApiBuilder);
        }

        // Direct configurator or reference
        if ($definition instanceof self || $definition instanceof ReferenceConfigurator) {
            return $definition;
        }

        // Parameter reference
        if (!method_exists($definition, 'describeQueryParameters')) {
            return new ReferenceConfigurator('#/components/parameters/'.ReferenceConfigurator::normalize($definition));
        }

        // Describe
        $configurator = new self($openApiBuilder);
        \call_user_func([$definition, 'describeQueryParameters'], $configurator, $openApiBuilder);

        return $configurator;
    }

    public function getParameters(): array
    {
        return $this->queryParameters;
    }
}
