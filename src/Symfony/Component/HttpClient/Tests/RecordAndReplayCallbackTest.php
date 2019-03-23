<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\RecordAndReplayCallback;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseNamingStrategyInterface;
use Symfony\Contracts\HttpClient\ResponseRecorderInterface;

class RecordAndReplayCallbackTest extends TestCase
{
    /**
     * @var ResponseNamingStrategyInterface
     */
    private $strategy;

    /**
     * @var ResponseRecorderInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $recorder;

    /**
     * @var RecordAndReplayCallback
     */
    private $responseFactory;

    /**
     * @var HttpClientInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $backupClient;

    /**
     * @var MockHttpClient
     */
    private $client;

    protected function setUp(): void
    {
        $this->backupClient = $this->createMock(HttpClientInterface::class);
        $this->recorder = $this->createMock(ResponseRecorderInterface::class);
        $this->strategy = $this->createMock(ResponseNamingStrategyInterface::class);
        $this->strategy->expects($this->any())->method('name')->willReturn('a_unique_name');
        $this->responseFactory = new RecordAndReplayCallback($this->strategy, $this->recorder, RecordAndReplayCallback::MODE_REPLAY, $this->backupClient);
        $this->client = new MockHttpClient($this->responseFactory);
    }

    public function testReplayOrRecord()
    {
        $this->responseFactory->setMode(RecordAndReplayCallback::MODE_REPLAY_OR_RECORD);
        $this->recorder->expects($this->once())->method('record');
        $this->recorder->expects($this->exactly(2))
            ->method('replay')
            ->willReturnOnConsecutiveCalls(null, new MockResponse('I\'m a replayed response.'));
        $this->backupClient->expects($this->once())
            ->method('request')
            ->willReturn(new MockResponse('I\'m a "live" response.'));

        $this->assertSame('I\'m a "live" response.', $this->client->request('GET', 'https://example.org/whatever')->getContent());
        $this->assertSame('I\'m a replayed response.', $this->client->request('GET', 'https://example.org/whatever')->getContent());
    }

    public function testReplay()
    {
        $this->recorder->expects($this->once())->method('replay')->willReturn(new MockResponse('I\'m a replayed response.'));
        $this->backupClient->expects($this->never())->method('request');

        $this->assertSame('I\'m a replayed response.', $this->client->request('GET', 'https://example.org/whatever')->getContent());
    }

    public function testRecord()
    {
        $this->responseFactory->setMode(RecordAndReplayCallback::MODE_RECORD);
        $this->recorder->expects($this->never())->method('replay');
        $this->backupClient->expects($this->once())->method('request')->willReturn(new MockResponse('I\'m a "live" response.'));

        $this->assertSame('I\'m a "live" response.', $this->client->request('GET', 'https://example.org/whatever')->getContent());
    }

    public function testReplayThrows()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to retrieve the response "a_unique_name".');

        $this->client->request('POST', 'https://example.org/whatever');
    }

    public function testInvalidMode()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Invalid provided mode "Coucou", available choices are: replay, record, replay_or_record');

        $this->responseFactory->setMode('Coucou');
    }
}
