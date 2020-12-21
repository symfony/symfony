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
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\SerializerDebugPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Normalizer\TestDenormalizer;
use Symfony\Component\Serializer\Tests\Normalizer\TestHybridNormalizer;
use Symfony\Component\Serializer\Tests\Normalizer\TestNormalizer;

class SerializerDebugPassTest extends TestCase
{
    private const NORMALIZER_TAG = 'serializer.normalizer';

    public function testProcess()
    {
        $serializerDebugPass = new SerializerDebugPass();
        $container = new ContainerBuilder();
        $container->register('serializer', SerializerInterface::class);

        $container->register('Test\normalizer', TestNormalizer::class)
            ->addTag(self::NORMALIZER_TAG);

        $container->register('Test\denormalizer', TestDenormalizer::class)
            ->addTag(self::NORMALIZER_TAG);

        $container->register('Test\hybridNormalizer', TestHybridNormalizer::class)
            ->addTag(self::NORMALIZER_TAG);

        $container->addCompilerPass($serializerDebugPass);

        $serializerDebugPass->process($container);

        $debugDefinitions = [
            'Test\normalizer' => 'debug.Test\normalizer',
            'Test\denormalizer' => 'debug.Test\denormalizer',
            'Test\hybridNormalizer' => 'debug.Test\hybridNormalizer',
        ];

        foreach ($debugDefinitions as $originalName => $decoratorName) {
            self::assertTrue($container->hasDefinition($decoratorName), 'Container should have definition: '.$decoratorName);
            $definition = $container->getDefinition($decoratorName);
            self::assertTrue($definition->hasTag('debug.normalizer'));
            $decoratedService = $definition->getDecoratedService();
            self::assertSame($originalName, $decoratedService[0]);
        }
    }
}
