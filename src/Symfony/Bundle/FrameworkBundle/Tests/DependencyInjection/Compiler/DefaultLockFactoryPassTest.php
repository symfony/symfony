<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DefaultLockFactoryPass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Middleware\DeduplicateMiddleware;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class DefaultLockFactoryPassTest extends TestCase
{
    public function testTheDefaultFactoryIsWiredWhenItIsRegistered()
    {
        $container = $this->createContainer(true);
        $container->setDefinition('limiter.foo', new ChildDefinition('limiter'));
        $container->setParameter('.rate_limiter.lock_factories', ['limiter.foo' => [2, null]]);

        new DefaultLockFactoryPass()->process($container);

        $this->assertEquals(new Reference('lock.factory'), $container->getDefinition('limiter.foo')->getArgument(2));
        $this->assertFalse($container->hasParameter('.rate_limiter.lock_factories'));
    }

    public function testTheAutomaticFactoryIsSkippedWhenNoneIsRegistered()
    {
        $container = $this->createContainer(false);
        $container->setDefinition('limiter.foo', new ChildDefinition('limiter'));
        $container->setParameter('.rate_limiter.lock_factories', ['limiter.foo' => [2, null]]);

        new DefaultLockFactoryPass()->process($container);

        $this->expectException(OutOfBoundsException::class);

        $container->getDefinition('limiter.foo')->getArgument(2);
    }

    public function testTheRequiredFactoryThrowsWhenNoneIsRegistered()
    {
        $container = $this->createContainer(false);
        $container->setDefinition('limiter.foo', new ChildDefinition('limiter'));
        $container->setParameter('.rate_limiter.lock_factories', ['limiter.foo' => [2, 'Rate limiter "foo"']]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Rate limiter "foo" requires the Lock component to be configured.');

        new DefaultLockFactoryPass()->process($container);
    }

    public function testTheDeduplicateMiddlewareIsKeptWhenTheDefaultFactoryIsRegistered()
    {
        $container = $this->createContainer(true);
        $this->registerBus($container);

        new DefaultLockFactoryPass()->process($container);

        $this->assertTrue($container->hasDefinition('messenger.middleware.deduplicate_middleware'));
        $this->assertSame([['id' => 'send_message'], ['id' => 'deduplicate_middleware']], $container->getParameter('messenger.bus.default.middleware'));
    }

    public function testTheDeduplicateMiddlewareIsRemovedWhenNoDefaultFactoryIsRegistered()
    {
        $container = $this->createContainer(false);
        $this->registerBus($container);

        new DefaultLockFactoryPass()->process($container);

        $this->assertFalse($container->hasDefinition('messenger.middleware.deduplicate_middleware'));
        $this->assertFalse($container->hasDefinition('messenger.failure.release_deduplication_lock_on_failure_listener'));
        $this->assertSame([['id' => 'send_message']], $container->getParameter('messenger.bus.default.middleware'));
    }

    private function createContainer(bool $withDefaultFactory): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('limiter', RateLimiterFactory::class)->setAbstract(true)->setArguments([null, null, null]);

        if ($withDefaultFactory) {
            $container->register('lock.default.factory', LockFactory::class);
            $container->setAlias('lock.factory', 'lock.default.factory');
        }

        return $container;
    }

    private function registerBus(ContainerBuilder $container): void
    {
        $container->register('messenger.middleware.deduplicate_middleware', DeduplicateMiddleware::class);
        $container->register('messenger.failure.release_deduplication_lock_on_failure_listener', \stdClass::class);
        $container->register('messenger.bus.default', \stdClass::class)->addTag('messenger.bus');
        $container->setParameter('messenger.bus.default.middleware', [['id' => 'send_message'], ['id' => 'deduplicate_middleware']]);
    }
}
