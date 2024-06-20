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
use Symfony\Component\Serializer\Normalizer\ChainNormalizer;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\Tests\Fixtures\ScalarDummy;

class ChainNormalizerTest extends TestCase
{
    public function testGetSupportedTypesCache()
    {
        $normalizer = $this->getMockBuilder(NormalizerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['normalize', 'supportsNormalization', 'getSupportedTypes'])
            ->getMock();
        $normalizer->expects($this->once())->method('getSupportedTypes')->willReturn(['*'=>true]);

        $chain = new ChainNormalizer([$normalizer]);
        $this->assertEquals(['*'=>true], $chain->getSupportedTypes('format'));
        $this->assertEquals(['*'=>true], $chain->getSupportedTypes('format'));
    }

    public function testGetSupportedTypesOrder()
    {
        $normalizerA = $this->getMockBuilder(NormalizerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['normalize', 'supportsNormalization', 'getSupportedTypes'])
            ->getMock();
        $normalizerA->expects($this->any())->method('getSupportedTypes')->willReturn(['foo'=>true]);
        $normalizerB = $this->getMockBuilder(NormalizerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['normalize', 'supportsNormalization', 'getSupportedTypes'])
            ->getMock();
        $normalizerB->expects($this->any())->method('getSupportedTypes')->willReturn(['foo'=>false]);

        $chain = new ChainNormalizer([$normalizerA, $normalizerB]);
        $this->assertEquals(['foo'=>true], $chain->getSupportedTypes('format'));

        $chain = new ChainNormalizer([$normalizerB, $normalizerA]);
        $this->assertEquals(['foo'=>true], $chain->getSupportedTypes('format'));
    }
}
