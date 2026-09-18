<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests\Fixtures\httprecorder\tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\Attribute\UseRecord;
use Symfony\Bridge\PhpUnit\HttpRecorder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\RecorderHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RecordMode extends TestCase
{
    #[UseRecord('../var/skipped.har')]
    public function testSkipped()
    {
        $this->markTestSkipped('The API key is needed to record this test.');
    }

    #[UseRecord('../var/recorded.har')]
    public function testRecorded()
    {
        $client = new RecorderHttpClient(new MockHttpClient(new MockResponse('live')), new HttpRecorder());

        $this->assertSame('live', $client->request('GET', 'https://example.com/')->getContent());
    }
}
