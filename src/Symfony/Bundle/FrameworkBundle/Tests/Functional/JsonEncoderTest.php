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
use Symfony\Component\JsonEncoder\DecoderInterface;
use Symfony\Component\JsonEncoder\EncoderInterface;
use Symfony\Component\TypeInfo\Type;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class JsonEncoderTest extends AbstractWebTestCase
{
    public function testEncode()
    {
        static::bootKernel(['test_case' => 'JsonEncoder']);

        /** @var EncoderInterface $encoder */
        $encoder = static::getContainer()->get('json_encoder.encoder.alias');

        $this->assertSame('{"@name":"DUMMY"}', (string) $encoder->encode(new Dummy(), Type::object(Dummy::class)));
    }

    public function testDecode()
    {
        static::bootKernel(['test_case' => 'JsonEncoder']);

        /** @var DecoderInterface $decoder */
        $decoder = static::getContainer()->get('json_encoder.decoder.alias');

        $expected = new Dummy();
        $expected->name = 'dummy';

        $this->assertEquals($expected, $decoder->decode('{"@name":"DUMMY"}', Type::object(Dummy::class)));
    }
}
