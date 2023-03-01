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

use Symfony\Component\OpenApi\Configurator\ServerConfigurator;
use Symfony\Component\OpenApi\Model\Server;

/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 * @author Selency Team <tech@selency.fr>
 */
trait ServersTrait
{
    /**
     * @var Server[]
     */
    private array $servers = [];

    public function server(ServerConfigurator|string $server): static
    {
        $this->servers[] = \is_string($server) ? (new ServerConfigurator($server))->build() : $server->build();

        return $this;
    }
}
