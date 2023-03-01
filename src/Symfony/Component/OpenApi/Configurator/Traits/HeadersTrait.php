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
trait HeadersTrait
{
    /**
     * @var array<string, Parameter|Reference>
     */
    private array $headers = [];

    public function header(string $name, ParameterConfigurator|ReferenceConfigurator|string $header): static
    {
        if (\is_string($header)) {
            $header = new ReferenceConfigurator('#/components/headers/'.ReferenceConfigurator::normalize($header));
        }

        if ($header instanceof ReferenceConfigurator) {
            $this->headers[$name] = $header->build();

            return $this;
        }

        $this->headers[$name] = $header->build(asHeader: true);

        return $this;
    }
}
