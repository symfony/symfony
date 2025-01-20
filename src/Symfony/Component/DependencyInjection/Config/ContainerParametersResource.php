<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Config;

use Symfony\Component\Config\Resource\ResourceInterface;

/**
 * Tracks container parameters.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @final
 */
class ContainerParametersResource implements ResourceInterface
{
    /**
     * @param array $parameters The container parameters to track
     */
    public function __construct(
        private array $parameters,
    ) {
    }

    public function __toString(): string
    {
        return 'container_parameters_'.hash('xxh128', serialize($this->parameters));
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
