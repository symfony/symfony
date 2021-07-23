<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\DebugAdapter;
use Symfony\Component\Cache\Adapter\DebugTagAwareAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\DependencyInjection\DebugCachePoolPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class DebugCachePoolPassTest extends TestCase
{
    /** @var DebugCachePoolPass */
    private $cachePoolPass;

    protected function setUp(): void
    {
        $this->cachePoolPass = new DebugCachePoolPass();
    }

    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.container_class', 'app');
        $container->setParameter('kernel.project_dir', 'foo');
        $container->setParameter('cache.exception_on_save', 'true');

        $container->register('cache.adapter.array', ArrayAdapter::class)
            ->addTag('cache.pool');

        $container->register('cache.adapter.filesystem_tag', FilesystemTagAwareAdapter::class)
            ->addTag('cache.pool');

        $this->cachePoolPass->process($container);

        /** @var Definition $decoratedAdapter */
        $decoratedAdapter = $container->get('cache.adapter.array');
        $this->assertEquals(DebugAdapter::class, $decoratedAdapter->getClass());

        /** @var Definition $decoratedAdapter */
        $decoratedAdapter = $container->get('cache.adapter.filesystem_tag');
        $this->assertEquals(DebugTagAwareAdapter::class, $decoratedAdapter->getClass());
    }
}
