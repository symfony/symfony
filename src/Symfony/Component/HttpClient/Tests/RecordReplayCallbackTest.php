<?php

/*
 *  This file is part of the Symfony package.
 *
 *  (c) Fabien Potencier <fabien@symfony.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use Psr\Log\Test\TestLogger as Logger;
use Symfony\Component\Filesystem\Tests\FilesystemTestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\Internal\ResponseRecorder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\RecordReplayCallback;
use Symfony\Component\HttpClient\Response\ResponseSerializer;

class RecordReplayCallbackTest extends FilesystemTestCase
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var RecordReplayCallback
     */
    private $callback;

    /**
     * @var MockHttpClient
     */
    private $client;

    protected function setUp(): void
    {
        parent::setUp();
        $recorder = new ResponseRecorder($this->workspace, new ResponseSerializer(), $this->filesystem);

        $this->logger = new Logger();
        $this->callback = new RecordReplayCallback($recorder);
        $this->callback->setLogger($this->logger);
        $this->client = new MockHttpClient($this->callback);
    }

    public function testReplayOrRecord()
    {
        $response = $this->client->request('GET', 'http://localhost:8057');
        $response->getHeaders(false);

        $this->logger->reset();
        $replayed = $this->client->request('GET', 'http://localhost:8057');
        $replayed->getHeaders(false);

        $this->assertSame($response->getContent(), $replayed->getContent());
        $this->assertSame($response->getInfo()['response_headers'], $replayed->getInfo()['response_headers']);

        $this->assertTrue($this->logger->hasDebugThatContains('Response replayed'), 'Response should be replayed');
    }

    public function testReplayThrowWhenNoRecordIsFound()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to replay response for GET request to "http://localhost:8057/" endpoint.');

        $this->callback->setMode(RecordReplayCallback::MODE_REPLAY);
        $response = $this->client->request('GET', 'http://localhost:8057', ['query' => ['foo' => 'bar']]);
        $response->getHeaders(false);
    }
}
