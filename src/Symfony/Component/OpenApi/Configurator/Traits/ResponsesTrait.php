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

use Symfony\Component\OpenApi\Configurator\ReferenceConfigurator;
use Symfony\Component\OpenApi\Configurator\ResponseConfigurator;
use Symfony\Component\OpenApi\Model\Reference;
use Symfony\Component\OpenApi\Model\Response;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
trait ResponsesTrait
{
    /**
     * @var array<string, Response|Reference>
     */
    private array $responses = [];

    public function response(string $name, ResponseConfigurator|ReferenceConfigurator|string $response): static
    {
        if (\is_string($response)) {
            $response = new ReferenceConfigurator('#/components/responses/'.ReferenceConfigurator::normalize($response));
        }

        $this->responses[$name] = $response->build();

        return $this;
    }
}
