<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OpenApi\Configurator\Traits;

use Symfony\Component\OpenApi\Configurator\ParameterConfigurator;
use Symfony\Component\OpenApi\Configurator\ReferenceConfigurator;
use Symfony\Component\OpenApi\Model\Parameter;
use Symfony\Component\OpenApi\Model\Reference;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
trait ParametersTrait
{
    /**
     * @var array<Parameter|Reference>
     */
    private array $parameters = [];

    public function parameter(ParameterConfigurator|ReferenceConfigurator|string $parameter): static
    {
        if (\is_string($parameter)) {
            $parameter = new ReferenceConfigurator('#/components/parameters/'.ReferenceConfigurator::normalize($parameter));
        }

        $this->parameters[] = $parameter->build();

        return $this;
    }
}
