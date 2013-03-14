<?php
/**
 *
 */

namespace Symfony\Tests\Component\Process;

use Symfony\Component\Process\ProcessStreams;

class ProcessStreamsTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $streams = new ProcessStreams();
        $this->assertInternalType('object', $streams);
    }

    /** @test */
    public function noStdinShouldNotCreateNoPipeAtIndexZero()
    {
        $streams = new ProcessStreams();
        $streams
            ->setDescriptorPipe($streams::STDIN)
            ->setDescriptorPipe($streams::STDOUT)
            ->prepareWrite($streams::STDIN, null)
            ->openProcess('php -v');

        $pipes = $streams->getPipes();
        $this->assertArrayHasKey(1, $pipes);
        $this->assertCount(1, $pipes);

        $this->assertTrue($streams->hasOpenPipes());

        list($selected) = $streams->selectAll(10);
        $this->assertSame(1, $selected, 'selected mismatch');

        $output = $streams->readPipe($streams::STDOUT, 8196);
        $this->assertStringStartsWith('PHP ', $output);

        list($selected, $readPipes) = $streams->selectAll(10);
        $this->assertSame(1, $selected, 'selected mismatch');
        $this->assertSame($pipes, $readPipes);

        $output = $streams->readPipe($streams::STDOUT, 8196);
        $this->assertEmpty($output);
        list($selected) = $streams->selectAll(10);
        $this->assertSame(false, $selected, 'selected mismatch');
    }
}
