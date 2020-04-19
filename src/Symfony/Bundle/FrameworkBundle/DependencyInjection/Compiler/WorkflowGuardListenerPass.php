<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Workflow\EventListener\NoSecurityGuardListener;

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class WorkflowGuardListenerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasParameter('workflow.has_guard_listeners')) {
            return;
        }

        $container->getParameterBag()->remove('workflow.has_guard_listeners');

        $servicesNeeded = [
            'security.token_storage',
            'security.authorization_checker',
            'security.authentication.trust_resolver',
            'security.role_hierarchy',
        ];

        $missingService = false;
        foreach ($servicesNeeded as $service) {
            if (!$container->has($service)) {
                $missingService = true;
                break;
            }
        }

        if ($missingService) {
            foreach ($container->findTaggedServiceIds('workflow.guard_listener') as $id => $attributes) {
                $definition = $container->getDefinition($id);
                $guardsConfiguration = $definition->getArgument(0);
                $definition->setClass(NoSecurityGuardListener::class);
                $definition->setArguments([
                    $guardsConfiguration,
                    new Reference('workflow.no_security.expression_language'),
                ]);
            }
        } elseif (!class_exists(Security::class)) {
            throw new LogicException('Cannot guard workflows as the Security component is not installed. Try running "composer require symfony/security-core".');
        }
    }
}
