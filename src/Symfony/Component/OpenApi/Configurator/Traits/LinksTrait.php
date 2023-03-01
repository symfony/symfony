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

use Symfony\Component\OpenApi\Configurator\LinkConfigurator;
use Symfony\Component\OpenApi\Configurator\ReferenceConfigurator;
use Symfony\Component\OpenApi\Model\Link;
use Symfony\Component\OpenApi\Model\Reference;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
trait LinksTrait
{
    /**
     * @var array<string, Link|Reference>
     */
    private array $links = [];

    public function link(string $name, LinkConfigurator|ReferenceConfigurator|string $link): static
    {
        if (\is_string($link)) {
            $link = new ReferenceConfigurator('#/components/links/'.ReferenceConfigurator::normalize($link));
        }

        $this->links[$name] = $link->build();

        return $this;
    }
}
