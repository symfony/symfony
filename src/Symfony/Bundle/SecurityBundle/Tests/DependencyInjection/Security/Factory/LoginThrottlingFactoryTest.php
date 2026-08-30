<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Security\Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\LoginThrottlingFactory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInvalidReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveReferencesToAliasesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\EventListener\LoginThrottlingListener;

class LoginThrottlingFactoryTest extends TestCase
{
    public function testTheDefaultLockFactoryIsUsedWhenItIsAvailable()
    {
        $this->skipIfLockIsNotInstalled();

        $container = $this->createContainer(true);
        $this->createAuthenticator($container, []);
        $this->resolveDefinitions($container);

        $this->assertSame('lock.default.factory', (string) $container->getDefinition('limiter._login_local_main')->getArgument(2));
        $this->assertSame('lock.default.factory', (string) $container->getDefinition('limiter._login_global_main')->getArgument(2));
    }

    public function testNoLockIsUsedWhenTheDefaultLockFactoryIsNotAvailable()
    {
        $container = $this->createContainer(false);
        $this->createAuthenticator($container, []);
        $this->resolveDefinitions($container);

        $this->assertNull($container->getDefinition('limiter._login_local_main')->getArgument(2));
        $this->assertNull($container->getDefinition('limiter._login_global_main')->getArgument(2));
    }

    public function testLockingCanBeTurnedOff()
    {
        $container = $this->createContainer(true);
        $this->createAuthenticator($container, ['lock_factory' => null]);
        $this->resolveDefinitions($container);

        $this->assertNull($container->getDefinition('limiter._login_local_main')->getArgument(2));
        $this->assertNull($container->getDefinition('limiter._login_global_main')->getArgument(2));
    }

    public function testAnExplicitLockFactoryIsUsed()
    {
        $this->skipIfLockIsNotInstalled();

        $container = $this->createContainer(true);
        $container->register('app.lock_factory', LockFactory::class);
        $this->createAuthenticator($container, ['lock_factory' => 'app.lock_factory']);
        $this->resolveDefinitions($container);

        $this->assertSame('app.lock_factory', (string) $container->getDefinition('limiter._login_local_main')->getArgument(2));
        $this->assertSame('app.lock_factory', (string) $container->getDefinition('limiter._login_global_main')->getArgument(2));
    }

    private function createContainer(bool $withLockFactory): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.secret', 's3cr3t');
        $container->setDefinition('limiter', (new Definition(RateLimiterFactory::class))->setAbstract(true)->setArguments([null, null, null]));
        $container->setDefinition('security.listener.login_throttling', (new Definition(LoginThrottlingListener::class))->setAbstract(true)->setArguments([null, null]));
        $container->register('cache.rate_limiter');

        if ($withLockFactory) {
            $container->register('lock.default.factory', LockFactory::class);
            $container->setAlias('lock.factory', new Alias('lock.default.factory', false));
        }

        return $container;
    }

    private function createAuthenticator(ContainerBuilder $container, array $config): void
    {
        $factory = new LoginThrottlingFactory();

        $factory->addConfiguration($nodeDefinition = new ArrayNodeDefinition('login_throttling'));
        $node = $nodeDefinition->getNode();

        $factory->createAuthenticator($container, 'main', $node->finalize($node->normalize($config)), 'user_provider');
    }

    private function resolveDefinitions(ContainerBuilder $container): void
    {
        (new ResolveChildDefinitionsPass())->process($container);
        (new ResolveReferencesToAliasesPass())->process($container);
        (new ResolveInvalidReferencesPass())->process($container);
    }

    private function skipIfLockIsNotInstalled(): void
    {
        if (!class_exists(LockFactory::class)) {
            $this->markTestSkipped('This test requires symfony/lock.');
        }
    }
}
