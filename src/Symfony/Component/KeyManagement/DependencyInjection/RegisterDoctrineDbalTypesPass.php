<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the encrypted Doctrine DBAL types when DoctrineBundle creates a connection, instead of when the kernel boots.
 *
 * DoctrineBundle registers the types of its configuration at that time, so the encrypted types can wrap them.
 * A request that doesn't use the database doesn't register any type either.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class RegisterDoctrineDbalTypesPass implements CompilerPassInterface
{
    private const string TYPES = 'key_management.doctrine_dbal.encrypted_types';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::TYPES) || !$container->hasParameter('doctrine.connections')) {
            return;
        }

        foreach ($container->getParameter('doctrine.connections') as $id) {
            $container->findDefinition($id)->setConfigurator([new Reference(self::TYPES), 'register']);
        }

        $container->getDefinition(self::TYPES)->setPublic(false);
    }
}
