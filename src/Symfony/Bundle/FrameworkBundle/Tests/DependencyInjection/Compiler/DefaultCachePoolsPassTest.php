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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DefaultCachePoolsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;

class DefaultCachePoolsPassTest extends TestCase
{
    public function testTheSessionHandlerReusesTheConnectionOfACachePool()
    {
        $container = new ContainerBuilder();
        $container->register('session.abstract_handler', AbstractSessionHandler::class)
            ->setArguments(['redis://localhost', []]);
        $container->register($id = '.cache_connection.'.ContainerBuilder::hash('redis://localhost'), \stdClass::class);

        new DefaultCachePoolsPass()->process($container);

        $this->assertEquals(new Reference($id), $container->getDefinition('session.abstract_handler')->getArgument(0));
    }

    public function testTheSessionHandlerKeepsItsDsnWhenNoCachePoolSharesIt()
    {
        $container = new ContainerBuilder();
        $container->register('session.abstract_handler', AbstractSessionHandler::class)
            ->setArguments(['redis://localhost', []]);

        new DefaultCachePoolsPass()->process($container);

        $this->assertSame('redis://localhost', $container->getDefinition('session.abstract_handler')->getArgument(0));
    }

    public function testAClaimCheckPoolMustDefineADefaultLifetime()
    {
        $container = new ContainerBuilder();
        $container->register('app.claim_check_pool')->addTag('cache.pool');
        $container->setParameter('.messenger.claim_check_pools', ['app.claim_check_pool' => 'async']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The cache pool "app.claim_check_pool" used by Messenger transport "async" for claim checks must define a "default_lifetime".');

        new DefaultCachePoolsPass()->process($container);
    }

    public function testAClaimCheckPoolWithADefaultLifetimeIsAccepted()
    {
        $container = new ContainerBuilder();
        $container->register('app.claim_check_pool')->addTag('cache.pool', ['default_lifetime' => 600]);
        $container->setParameter('.messenger.claim_check_pools', ['app.claim_check_pool' => 'async']);

        new DefaultCachePoolsPass()->process($container);

        $this->assertFalse($container->hasParameter('.messenger.claim_check_pools'));
    }
}
