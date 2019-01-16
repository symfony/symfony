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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\SerializerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests for the SerializerPass class.
 *
 * @group legacy
 *
 * @author Javier Lopez <f12loalf@gmail.com>
 */
class SerializerPassTest extends TestCase
{
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage You must tag at least one service as "serializer.normalizer" to use the "serializer" service
     */
    public function testThrowExceptionWhenNoNormalizers()
    {
        $container = new ContainerBuilder();
        $container->register('serializer');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage You must tag at least one service as "serializer.encoder" to use the "serializer" service
     */
    public function testThrowExceptionWhenNoEncoders()
    {
        $container = new ContainerBuilder();
        $container->register('serializer')
            ->addArgument([])
            ->addArgument([]);
        $container->register('normalizer')->addTag('serializer.normalizer');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);
    }

    public function testServicesAreOrderedAccordingToPriority()
    {
        $container = new ContainerBuilder();

        $definition = $container->register('serializer')->setArguments([null, null]);
        $container->register('n2')->addTag('serializer.normalizer', ['priority' => 100])->addTag('serializer.encoder', ['priority' => 100]);
        $container->register('n1')->addTag('serializer.normalizer', ['priority' => 200])->addTag('serializer.encoder', ['priority' => 200]);
        $container->register('n3')->addTag('serializer.normalizer')->addTag('serializer.encoder');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $expected = [
            new Reference('n1'),
            new Reference('n2'),
            new Reference('n3'),
        ];
        $this->assertEquals($expected, $definition->getArgument(0));
        $this->assertEquals($expected, $definition->getArgument(1));
    }
}
