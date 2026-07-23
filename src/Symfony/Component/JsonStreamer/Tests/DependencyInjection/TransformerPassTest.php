<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\JsonStreamer\DependencyInjection\TransformerPass;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer\BooleanToStringValueTransformer;
use Symfony\Component\JsonStreamer\Transformer\DateTimeValueObjectTransformer;
use Symfony\Component\JsonStreamer\Transformer\PropertyValueTransformerInterface;
use Symfony\Component\JsonStreamer\Transformer\ValueObjectTransformerInterface;

class TransformerPassTest extends TestCase
{
    public function testDoNothingIfStreamWriterNotDefined()
    {
        $container = new ContainerBuilder();
        $container->register('json_streamer.stream_reader')->setArguments([null]);

        (new TransformerPass())->process($container);

        $this->assertSame([null], $container->getDefinition('json_streamer.stream_reader')->getArguments());
    }

    public function testCollectPropertyTransformers()
    {
        $container = new ContainerBuilder();

        $container->register('json_streamer.stream_reader')->setArguments([null]);
        $container->register('json_streamer.stream_writer')->setArguments([null]);
        $container->register('.json_streamer.cache_warmer.streamer')->setArguments([null, null]);

        $container->register('my_transformer')
            ->setClass(BooleanToStringValueTransformer::class)
            ->addTag('json_streamer.property_value_transformer');

        (new TransformerPass())->process($container);

        $locator = $container->getDefinition('json_streamer.stream_reader')->getArgument(0);
        $this->assertInstanceOf(ServiceLocatorArgument::class, $locator);

        $map = $locator->getValues();
        $this->assertArrayHasKey('my_transformer', $map);
        $this->assertEquals(new Reference('my_transformer'), $map['my_transformer']);
    }

    public function testCollectValueObjectTransformers()
    {
        $container = new ContainerBuilder();

        $container->register('json_streamer.stream_reader')->setArguments([null]);
        $container->register('json_streamer.stream_writer')->setArguments([null]);
        $container->register('.json_streamer.cache_warmer.streamer')->setArguments([null, null]);

        $container->register('my_object_transformer')
            ->setClass(DateTimeValueObjectTransformer::class)
            ->addTag('json_streamer.value_object_transformer');

        (new TransformerPass())->process($container);

        $locator = $container->getDefinition('json_streamer.stream_writer')->getArgument(0);
        $this->assertInstanceOf(ServiceLocatorArgument::class, $locator);

        $map = $locator->getValues();
        $this->assertArrayHasKey(DateTimeValueObjectTransformer::getValueObjectClassName(), $map);
        $this->assertEquals(new Reference('my_object_transformer'), $map[DateTimeValueObjectTransformer::getValueObjectClassName()]);
    }

    public function testThrowOnInvalidPropertyTransformer()
    {
        $container = new ContainerBuilder();

        $container->register('json_streamer.stream_reader')->setArguments([null]);
        $container->register('json_streamer.stream_writer')->setArguments([null]);
        $container->register('.json_streamer.cache_warmer.streamer')->setArguments([null, null]);

        $container->register('invalid_transformer')
            ->setClass(\stdClass::class)
            ->addTag('json_streamer.property_value_transformer');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The service "invalid_transformer" tagged "json_streamer.property_value_transformer" must implement "%s".', PropertyValueTransformerInterface::class));

        (new TransformerPass())->process($container);
    }

    public function testThrowOnInvalidValueObjectTransformer()
    {
        $container = new ContainerBuilder();

        $container->register('json_streamer.stream_reader')->setArguments([null]);
        $container->register('json_streamer.stream_writer')->setArguments([null]);
        $container->register('.json_streamer.cache_warmer.streamer')->setArguments([null, null]);

        $container->register('invalid_object_transformer')
            ->setClass(\stdClass::class)
            ->addTag('json_streamer.value_object_transformer');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The service "invalid_object_transformer" tagged "json_streamer.value_object_transformer" must implement "%s".', ValueObjectTransformerInterface::class));

        (new TransformerPass())->process($container);
    }
}
