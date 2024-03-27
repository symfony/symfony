<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\JsonEncoder\DecoderInterface;
use Symfony\Component\JsonEncoder\DependencyInjection\JsonEncoderPass;
use Symfony\Component\JsonEncoder\EncoderInterface;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithAttributesUsingServices;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithFormatterAttributes;

class JsonEncoderPassTest extends TestCase
{
    public function testInjectEncodableClassNames()
    {
        $container = new ContainerBuilder();

        $container->register('json_encoder.encoder');
        $container->register('.json_encoder.cache_warmer.encoder_decoder')->setArguments([null]);
        $container->register('.json_encoder.cache_warmer.lazy_ghost')->setArguments([null]);

        $container->register(ClassicDummy::class, ClassicDummy::class)->addTag('json_encoder.encodable');
        $container->register(DummyWithFormatterAttributes::class, DummyWithFormatterAttributes::class)->addTag('json_encoder.encodable');
        $container->register(DummyWithAttributesUsingServices::class, DummyWithAttributesUsingServices::class)->addTag('json_encoder.encodable');

        (new JsonEncoderPass())->process($container);

        $encoableClasses = [ClassicDummy::class, DummyWithFormatterAttributes::class, DummyWithAttributesUsingServices::class];

        $this->assertSame($encoableClasses, $container->getDefinition('.json_encoder.cache_warmer.encoder_decoder')->getArgument(0));
        $this->assertSame($encoableClasses, $container->getDefinition('.json_encoder.cache_warmer.lazy_ghost')->getArgument(0));
    }

    public function testRegisterAliases()
    {
        $container = new ContainerBuilder();

        $container->register('json_encoder.encoder');
        $container->register('.json_encoder.cache_warmer.encoder_decoder')->setArguments([null]);
        $container->register('.json_encoder.cache_warmer.lazy_ghost')->setArguments([null]);

        (new JsonEncoderPass())->process($container);

        $this->assertEquals('json_encoder.encoder', (string) $container->getAlias(sprintf('%s $jsonEncoder', EncoderInterface::class)));
        $this->assertEquals('json_encoder.decoder', (string) $container->getAlias(sprintf('%s $jsonDecoder', DecoderInterface::class)));
    }
}
