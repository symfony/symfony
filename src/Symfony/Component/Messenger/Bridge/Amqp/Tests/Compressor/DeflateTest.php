<?php

namespace Compressor;

use Symfony\Component\Messenger\Bridge\Amqp\Compressor\Deflate;
use PHPUnit\Framework\TestCase;

class DeflateTest extends TestCase
{
    public function testDecompress()
    {
        $compressor = new Deflate();

        $actual = $compressor->decompress('string no compressed');
        $this->assertEquals('string no compressed', $actual);

        $actual = $compressor->decompress(gzdeflate('string compressed'));
        $this->assertEquals('string compressed', $actual);
    }
}
