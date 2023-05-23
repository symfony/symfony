<?php

namespace Compressor;

use Symfony\Component\Messenger\Bridge\Amqp\Compressor\Gzip;
use PHPUnit\Framework\TestCase;

class GzipTest extends TestCase
{
    public function testDecompress()
    {
        $compressor = new Gzip();

        $actual = $compressor->decompress('string no compressed');
        $this->assertEquals('string no compressed', $actual);

        $actual = $compressor->decompress(gzencode('string compressed'));
        $this->assertEquals('string compressed', $actual);
    }
}
