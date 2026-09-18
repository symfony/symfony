<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\HttpRecorder;
use Symfony\Component\HttpClient\Recorder\RecorderConfigurationInterface;
use Symfony\Component\HttpClient\Recorder\RecorderMode;

class HttpRecorderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!interface_exists(RecorderConfigurationInterface::class)) {
            $this->markTestSkipped('symfony/http-client >= 8.2 is required.');
        }
    }

    protected function tearDown(): void
    {
        if (interface_exists(RecorderConfigurationInterface::class)) {
            HttpRecorder::reset();
        }
    }

    public function testDefaultValues()
    {
        $recorder = new HttpRecorder();

        $this->assertSame(RecorderMode::Passthrough, $recorder->getMode());
        $this->assertSame('', $recorder->getHarFilePath());
        $this->assertSame([], $recorder->getConsumedEntries());
        $this->assertSame([], HttpRecorder::getMisses());
    }

    public function testConfigureIsVisibleFromEveryInstance()
    {
        HttpRecorder::configure(RecorderMode::Missing, '/tmp/x.har');

        $recorder = new HttpRecorder();

        $this->assertSame(RecorderMode::Missing, $recorder->getMode());
        $this->assertSame('/tmp/x.har', $recorder->getHarFilePath());
    }

    public function testConsumedEntriesAndMissesAreSharedByEveryInstanceUntilTheNextConfiguration()
    {
        HttpRecorder::configure(RecorderMode::Replay, '/tmp/x.har');

        (new HttpRecorder())->consumeEntry(0);
        (new HttpRecorder())->consumeEntry(1);
        (new HttpRecorder())->reportMiss('GET', 'https://example.com/');

        $this->assertSame([0, 1], (new HttpRecorder())->getConsumedEntries());
        $this->assertSame(['GET https://example.com/'], HttpRecorder::getMisses());

        HttpRecorder::configure(RecorderMode::Replay, '/tmp/x.har');

        $this->assertSame([], (new HttpRecorder())->getConsumedEntries());
        $this->assertSame([], HttpRecorder::getMisses());
    }

    public function testRecordingContinuesTheEntriesRecordedEarlierIntoTheSameFile()
    {
        $shared = '/tmp/'.uniqid('shared_', true).'.har';

        HttpRecorder::configure(RecorderMode::Record, $shared);
        (new HttpRecorder())->consumeEntry(0);

        HttpRecorder::configure(RecorderMode::Record, '/tmp/'.uniqid('other_', true).'.har');
        $this->assertSame([], (new HttpRecorder())->getConsumedEntries());

        HttpRecorder::configure(RecorderMode::Replay, $shared);
        $this->assertSame([], (new HttpRecorder())->getConsumedEntries());

        HttpRecorder::configure(RecorderMode::Record, $shared);
        $this->assertSame([0], (new HttpRecorder())->getConsumedEntries());
    }

    public function testReset()
    {
        HttpRecorder::configure(RecorderMode::Replay, '/tmp/x.har');

        $recorder = new HttpRecorder();
        $recorder->consumeEntry(0);
        $recorder->reportMiss('GET', 'https://example.com/');

        HttpRecorder::reset();

        $this->assertSame(RecorderMode::Passthrough, $recorder->getMode());
        $this->assertSame('', $recorder->getHarFilePath());
        $this->assertSame([], $recorder->getConsumedEntries());
        $this->assertSame([], HttpRecorder::getMisses());
    }
}
