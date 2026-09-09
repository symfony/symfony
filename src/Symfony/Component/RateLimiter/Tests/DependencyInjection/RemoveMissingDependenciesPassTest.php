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
use Symfony\Component\RateLimiter\DependencyInjection\RemoveMissingDependenciesPass;

class RemoveMissingDependenciesPassTest extends TestCase
{
    public function testTheCachePoolIsKeptWhenTheAppPoolExists()
    {
        $container = $this->createContainer();
        $container->register('cache.app');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertTrue($container->hasDefinition('cache.rate_limiter'));
    }

    public function testTheCachePoolIsDroppedWhenTheAppPoolIsMissing()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->hasDefinition('cache.rate_limiter'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.rate_limiter', new ChildDefinition('cache.app'));

        return $container;
    }
}
