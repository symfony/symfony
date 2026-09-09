<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\DependencyInjection\DefaultLockFactoryPass;
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
}
