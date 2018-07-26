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
 * @author Javier Lopez <f12loalf@gmail.com>
 */
class SerializerPassTest extends TestCase
{
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage You must tag at least one service as "serializer.normalizer" to use the Serializer service
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
     * @expectedExceptionMessage You must tag at least one service as "serializer.encoder" to use the Serializer service
     */
    public function testThrowExceptionWhenNoEncoders()
    {
        $container = new ContainerBuilder();
        $container->register('serializer')
            ->addArgument(array())
            ->addArgument(array());
        $container->register('normalizer')->addTag('serializer.normalizer');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);
    }

    public function testServicesAreOrderedAccordingToPriority()
    {
        $container = new ContainerBuilder();
        $serializerDefinition = $container->register('serializer')
            ->addArgument(array())
            ->addArgument(array());
        $container->register('normalizer3')->addTag('serializer.normalizer');
        $container->register('normalizer1')->addTag('serializer.normalizer', array('priority' => 200));
        $container->register('normalizer2')->addTag('serializer.normalizer', array('priority' => 100));
        $container->register('encoder')->addTag('serializer.encoder');

        $serializerPass = new SerializerPass();
        $serializerPass->process($container);

        $this->assertEquals(array(new Reference('normalizer1'), new Reference('normalizer2'), new Reference('normalizer3')), $serializerDefinition->getArgument(0));
    }
}
