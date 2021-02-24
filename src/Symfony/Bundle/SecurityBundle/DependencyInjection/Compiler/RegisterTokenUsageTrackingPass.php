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

use Symfony\Bridge\Monolog\Processor\ProcessorInterface;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Injects the session tracker enabler in "security.context_listener" + binds "security.untracked_token_storage" to ProcessorInterface instances.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class RegisterTokenUsageTrackingPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has('security.untracked_token_storage')) {
            return;
        }

        $processorAutoconfiguration = $container->registerForAutoconfiguration(ProcessorInterface::class);
        $processorAutoconfiguration->setBindings($processorAutoconfiguration->getBindings() + [
            TokenStorageInterface::class => new BoundArgument(new Reference('security.untracked_token_storage'), false),
        ]);

        if (!$container->has('session.factory') && !$container->has('session.storage')) {
            $container->setAlias('security.token_storage', 'security.untracked_token_storage')->setPublic(true);
            $container->getDefinition('security.untracked_token_storage')->addTag('kernel.reset', ['method' => 'reset']);
        } elseif ($container->hasDefinition('security.context_listener')) {
            $tokenStorageClass = $container->getParameterBag()->resolveValue($container->findDefinition('security.token_storage')->getClass());

            if (method_exists($tokenStorageClass, 'enableUsageTracking')) {
                $container->getDefinition('security.context_listener')
                    ->setArgument(6, [new Reference('security.token_storage'), 'enableUsageTracking']);
            }
        }
    }
}
