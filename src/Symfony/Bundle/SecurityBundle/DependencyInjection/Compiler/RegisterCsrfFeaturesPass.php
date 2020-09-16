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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Http\EventListener\CsrfProtectionListener;
use Symfony\Component\Security\Http\EventListener\CsrfTokenClearingLogoutListener;

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @internal
 */
class RegisterCsrfFeaturesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $this->registerCsrfProtectionListener($container);
        $this->registerLogoutHandler($container);
    }

    private function registerCsrfProtectionListener(ContainerBuilder $container)
    {
        if (!$container->has('security.authenticator.manager') || !$container->has('security.csrf.token_manager')) {
            return;
        }

        $container->register('security.listener.csrf_protection', CsrfProtectionListener::class)
            ->addArgument(new Reference('security.csrf.token_manager'))
            ->addTag('kernel.event_subscriber')
            ->setPublic(false);
    }

    protected function registerLogoutHandler(ContainerBuilder $container)
    {
        if (!$container->has('security.logout_listener') || !$container->has('security.csrf.token_storage')) {
            return;
        }

        $csrfTokenStorage = $container->findDefinition('security.csrf.token_storage');
        $csrfTokenStorageClass = $container->getParameterBag()->resolveValue($csrfTokenStorage->getClass());

        if (!is_subclass_of($csrfTokenStorageClass, 'Symfony\Component\Security\Csrf\TokenStorage\ClearableTokenStorageInterface')) {
            return;
        }

        $container->register('security.logout.listener.csrf_token_clearing', CsrfTokenClearingLogoutListener::class)
            ->addArgument(new Reference('security.csrf.token_storage'))
            ->addTag('kernel.event_subscriber')
            ->setPublic(false);
    }
}
