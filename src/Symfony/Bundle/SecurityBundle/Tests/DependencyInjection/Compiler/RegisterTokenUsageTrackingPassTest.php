<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\RegisterTokenUsageTrackingPass;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Session\SessionFactory;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\UsageTrackingTokenStorage;
use Symfony\Component\Security\Http\Firewall\ContextListener;

class RegisterTokenUsageTrackingPassTest extends TestCase
{
    public function testTokenStorageIsUntrackedIfSessionIsMissing()
    {
        $container = new ContainerBuilder();
        $container->register('security.untracked_token_storage', TokenStorage::class);

        $compilerPass = new RegisterTokenUsageTrackingPass();
        $compilerPass->process($container);

        $this->assertTrue($container->hasAlias('security.token_storage'));
        $this->assertEquals(new Alias('security.untracked_token_storage', true), $container->getAlias('security.token_storage'));
    }

    public function testContextListenerIsNotModifiedIfTokenStorageDoesNotSupportUsageTracking()
    {
        $container = new ContainerBuilder();

        $container->setParameter('security.token_storage.class', TokenStorage::class);
        $container->register('security.context_listener', ContextListener::class)
            ->setArguments([
                new Reference('security.untracked_token_storage'),
                [],
                'main',
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('security.authentication.trust_resolver'),
            ]);
        $container->register('security.token_storage', '%security.token_storage.class%');
        $container->register('security.untracked_token_storage', TokenStorage::class);

        $compilerPass = new RegisterTokenUsageTrackingPass();
        $compilerPass->process($container);

        $this->assertCount(6, $container->getDefinition('security.context_listener')->getArguments());
    }

    public function testContextListenerEnablesUsageTrackingIfSupportedByTokenStorage()
    {
        $container = new ContainerBuilder();

        $container->setParameter('security.token_storage.class', UsageTrackingTokenStorage::class);
        $container->register('session.factory', SessionFactory::class);
        $container->register('security.context_listener', ContextListener::class)
            ->setArguments([
                new Reference('security.untracked_token_storage'),
                [],
                'main',
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('security.authentication.trust_resolver'),
            ]);
        $container->register('security.token_storage', '%security.token_storage.class%');
        $container->register('security.untracked_token_storage', TokenStorage::class);

        $compilerPass = new RegisterTokenUsageTrackingPass();
        $compilerPass->process($container);

        $contextListener = $container->getDefinition('security.context_listener');

        $this->assertCount(7, $container->getDefinition('security.context_listener')->getArguments());
        $this->assertEquals([new Reference('security.token_storage'), 'enableUsageTracking'], $contextListener->getArgument(6));
    }
}
