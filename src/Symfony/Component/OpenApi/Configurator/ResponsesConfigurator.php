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

use Symfony\Component\OpenApi\Model\Reference;
use Symfony\Component\OpenApi\Model\Response;
use Symfony\Component\OpenApi\Model\Responses;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
class ResponsesConfigurator
{
    use Traits\ExtensionsTrait;
    use Traits\ResponsesTrait;

    private Response|Reference|null $default = null;

    public function build(): Responses
    {
        return new Responses($this->default, $this->responses ?: null, $this->specificationExtensions);
    }

    public function default(ResponseConfigurator|ReferenceConfigurator|string $response): static
    {
        if (\is_string($response)) {
            $response = new ReferenceConfigurator('#/components/responses/'.ReferenceConfigurator::normalize($response));
        }

        $this->default = $response->build();

        return $this;
    }
}
