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
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\LoggerAwarePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Unit tests for Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\LoggerAwarePass.
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class LoggerAwarePassTest extends TestCase
{
    /**
     * @covers \Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\LoggerAwarePass::process()
     */
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('logger', LoggerInterface::class);

        $definition = $container->register('foo', get_class($this->createMock(LoggerAwareInterface::class)));
        $container->register('bar', 'stdClass');
        $this->assertFalse(
            $definition->hasMethodCall('setLogger'),
            'Service should not have "setLogger" method call before Pass execution.'
        );

        (new LoggerAwarePass())->process($container);

        $this->assertTrue($definition->hasMethodCall('setLogger'), 'Service should have "setLogger" method call.');
        $this->assertFalse(
            $container->findDefinition('bar')->hasMethodCall('setLogger'),
            '"bar" service should not be affected'
        );
    }
}
