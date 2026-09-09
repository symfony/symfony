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

    public function testDefaultsToPassthrough()
    {
        $recorder = new HttpRecorder();

        $this->assertSame(RecorderMode::Passthrough, $recorder->getMode());
        $this->assertSame('', $recorder->getHarFilePath());
        $this->assertFalse($recorder->shouldRecordIfMissing());
    }

    public function testConfigureIsVisibleFromEveryInstance()
    {
        HttpRecorder::configure(RecorderMode::Replay, '/tmp/x.har', true);

        $recorder = new HttpRecorder();

        $this->assertSame(RecorderMode::Replay, $recorder->getMode());
        $this->assertSame('/tmp/x.har', $recorder->getHarFilePath());
        $this->assertTrue($recorder->shouldRecordIfMissing());
    }

    public function testReset()
    {
        HttpRecorder::configure(RecorderMode::Replay, '/tmp/x.har', true);
        HttpRecorder::reset();

        $recorder = new HttpRecorder();

        $this->assertSame(RecorderMode::Passthrough, $recorder->getMode());
        $this->assertSame('', $recorder->getHarFilePath());
        $this->assertFalse($recorder->shouldRecordIfMissing());
    }
}
