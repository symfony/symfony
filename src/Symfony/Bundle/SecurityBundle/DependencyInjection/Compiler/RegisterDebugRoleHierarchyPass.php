<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Bundle\SecurityBundle\Debug\DebugRoleHierarchy;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class RegisterDebugRoleHierarchyPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('security.role_hierarchy')) {
            $container->removeDefinition('security.command.debug_role_hierarchy');

            return;
        }

        $definition = $container->findDefinition('security.role_hierarchy');

        if (RoleHierarchy::class === $definition->getClass()) {
            $hierarchy = $definition->getArgument(0);
            $definition = new Definition(DebugRoleHierarchy::class, [$hierarchy]);
        }
        $container->setDefinition('debug.security.role_hierarchy', $definition);
    }
}
