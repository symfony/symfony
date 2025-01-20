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
use Symfony\Component\JsonEncoder\DependencyInjection\EncodablePass;

class EncodablePassTest extends TestCase
{
    public function testSetEncodableClassNames()
    {
        $container = new ContainerBuilder();

        $container->register('json_encoder.encoder');
        $container->register('.json_encoder.cache_warmer.encoder_decoder')->setArguments([null]);
        $container->register('.json_encoder.cache_warmer.lazy_ghost')->setArguments([null]);

        $container->register('encodable')->setClass('Foo')->addTag('json_encoder.encodable');
        $container->register('abstractEncodable')->setClass('Bar')->addTag('json_encoder.encodable')->setAbstract(true);
        $container->register('notEncodable')->setClass('Baz');

        $pass = new EncodablePass();
        $pass->process($container);

        $encoderDecoderCacheWarmer = $container->getDefinition('.json_encoder.cache_warmer.encoder_decoder');
        $lazyGhostCacheWarmer = $container->getDefinition('.json_encoder.cache_warmer.lazy_ghost');

        $expectedEncodableClassNames = ['Foo'];

        $this->assertSame($expectedEncodableClassNames, $encoderDecoderCacheWarmer->getArgument(0));
        $this->assertSame($expectedEncodableClassNames, $lazyGhostCacheWarmer->getArgument(0));
    }
}
