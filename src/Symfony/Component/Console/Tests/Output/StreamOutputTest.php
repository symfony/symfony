<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Output;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ForwardCompatTestTrait;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\StreamOutput;

class StreamOutputTest extends TestCase
{
    use ForwardCompatTestTrait;

    protected $stream;

    private function doSetUp()
    {
        $this->stream = fopen('php://memory', 'a', false);
    }

    private function doTearDown()
    {
        $this->stream = null;
    }

    public function testConstructor()
    {
        $output = new StreamOutput($this->stream, Output::VERBOSITY_QUIET, true);
        $this->assertEquals(Output::VERBOSITY_QUIET, $output->getVerbosity(), '__construct() takes the verbosity as its first argument');
        $this->assertTrue($output->isDecorated(), '__construct() takes the decorated flag as its second argument');
    }

    public function testStreamIsRequired()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('The StreamOutput class needs a stream as its first argument.');
        new StreamOutput('foo');
    }

    public function testGetStream()
    {
        $output = new StreamOutput($this->stream);
        $this->assertEquals($this->stream, $output->getStream(), '->getStream() returns the current stream');
    }

    public function testDoWrite()
    {
        $output = new StreamOutput($this->stream);
        $output->writeln('foo');
        rewind($output->getStream());
        $this->assertEquals('foo'.PHP_EOL, stream_get_contents($output->getStream()), '->doWrite() writes to the stream');
    }
}
