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
use Symfony\Component\Config\ResourceCheckerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ContainerParametersResourceChecker implements ResourceCheckerInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function supports(ResourceInterface $metadata): bool
    {
        return $metadata instanceof ContainerParametersResource;
    }

    public function isFresh(ResourceInterface $resource, int $timestamp): bool
    {
        foreach ($resource->getParameters() as $key => $value) {
            if (!$this->container->hasParameter($key) || $this->container->getParameter($key) !== $value) {
                return false;
            }
        }

        return true;
    }
}
