<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Tests\Functional\app\JsonEncoder\Dto\Dummy;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\JsonEncoder\DecoderInterface;
use Symfony\Component\JsonEncoder\EncoderInterface;
use Symfony\Component\TypeInfo\Type;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class JsonEncoderTest extends AbstractWebTestCase
{
    protected function setUp(): void
    {
        static::bootKernel(['test_case' => 'JsonEncoder']);
    }

    public function testEncode()
    {
        /** @var EncoderInterface $encoder */
        $encoder = static::getContainer()->get('json_encoder.encoder.alias');

        $this->assertSame('{"@name":"DUMMY","range":"10..20"}', (string) $encoder->encode(new Dummy(), Type::object(Dummy::class)));
    }

    public function testDecode()
    {
        /** @var DecoderInterface $decoder */
        $decoder = static::getContainer()->get('json_encoder.decoder.alias');

        $expected = new Dummy();
        $expected->name = 'dummy';
        $expected->range = [0, 1];

        $this->assertEquals($expected, $decoder->decode('{"@name": "DUMMY", "range": "0..1"}', Type::object(Dummy::class)));
    }

    public function testWarmupEncodableClasses()
    {
        /** @var Filesystem $fs */
        $fs = static::getContainer()->get('filesystem');

        $encodersDir = \sprintf('%s/json_encoder/encoder/', static::getContainer()->getParameter('kernel.cache_dir'));

        // clear already created encoders
        if ($fs->exists($encodersDir)) {
            $fs->remove($encodersDir);
        }

        static::getContainer()->get('json_encoder.cache_warmer.encoder_decoder.alias')->warmUp(static::getContainer()->getParameter('kernel.cache_dir'));

        $this->assertFileExists($encodersDir);
        $this->assertCount(1, glob($encodersDir.'/*'));
    }
}
