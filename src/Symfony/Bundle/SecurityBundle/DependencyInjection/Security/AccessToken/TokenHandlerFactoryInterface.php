<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\AccessToken;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Allows creating configurable token handlers.
 *
 * @experimental
 */
interface TokenHandlerFactoryInterface
{
    /**
     * Creates a generic token handler service.
     */
    public function create(ContainerBuilder $container, string $id, array|string $config): void;

    /**
     * Gets a generic token handler configuration key.
     */
    public function getKey(): string;

    /**
     * Adds a generic token handler configuration.
     */
    public function addConfiguration(NodeBuilder $node): void;
}
