<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\ChainDenormalizer;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\Tests\Fixtures\ScalarDummy;

class ChainDenormalizerTest extends TestCase
{
    public function testGetSupportedTypesCache()
    {
        $normalizer = $this->getMockBuilder(DenormalizerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['denormalize', 'supportsDenormalization', 'getSupportedTypes'])
            ->getMock();
        $normalizer->expects($this->once())->method('getSupportedTypes')->willReturn(['*'=>true]);

        $chain = new ChainDenormalizer([$normalizer]);
        $this->assertEquals(['*'=>true], $chain->getSupportedTypes('format'));
        $this->assertEquals(['*'=>true], $chain->getSupportedTypes('format'));
    }

    public function testGetSupportedTypesOrder()
    {
        $normalizerA = $this->getMockBuilder(DenormalizerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['denormalize', 'supportsDenormalization', 'getSupportedTypes'])
            ->getMock();
        $normalizerA->expects($this->any())->method('getSupportedTypes')->willReturn(['foo'=>true]);
        $normalizerB = $this->getMockBuilder(DenormalizerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['denormalize', 'supportsDenormalization', 'getSupportedTypes'])
            ->getMock();
        $normalizerB->expects($this->any())->method('getSupportedTypes')->willReturn(['foo'=>false]);

        $chain = new ChainDenormalizer([$normalizerA, $normalizerB]);
        $this->assertEquals(['foo'=>true], $chain->getSupportedTypes('format'));

        $chain = new ChainDenormalizer([$normalizerB, $normalizerA]);
        $this->assertEquals(['foo'=>true], $chain->getSupportedTypes('format'));
    }
}
