<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.1
 */
interface EntryPointFactoryInterface
{
    /**
     * Register the entry point on the container and returns the service ID.
     *
     * This does not mean that the entry point is also used. This is managed
     * by the "entry_point" firewall setting.
     */
    public function registerEntryPoint(ContainerBuilder $container, string $id, array $config): ?string;
}
