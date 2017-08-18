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

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
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

        if (!$container->has('security.token_storage')) {
            throw new LogicException('The "security.token_storage" service is needed to be able to use the workflow guard listener.');
        }

        if (!$container->has('security.authorization_checker')) {
            throw new LogicException('The "security.authorization_checker" service is needed to be able to use the workflow guard listener.');
        }

        if (!$container->has('security.authentication.trust_resolver')) {
            throw new LogicException('The "security.authentication.trust_resolver" service is needed to be able to use the workflow guard listener.');
        }

        if (!$container->has('security.role_hierarchy')) {
            throw new LogicException('The "security.role_hierarchy" service is needed to be able to use the workflow guard listener.');
        }
    }
}
