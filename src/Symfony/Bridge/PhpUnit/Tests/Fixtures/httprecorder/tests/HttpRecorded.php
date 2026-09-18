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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\Attribute\UseRecord;
use Symfony\Bridge\PhpUnit\HttpRecorder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\RecorderHttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[UseRecord('../records/shared.har')]
class HttpRecorded extends TestCase
{
    public function testClassLevelRecord()
    {
        $this->assertSame('shared', $this->request());
    }

    #[UseRecord]
    public function testMethodLevelRecord()
    {
        $this->assertSame('method', $this->request());
    }

    public function testCursorSurvivesTheClientBeingRecreated()
    {
        $this->assertSame('shared', $this->request());
        $this->assertSame('shared again', $this->request());
    }

    #[UseRecord]
    #[DataProvider('provideDataSets')]
    public function testDataSet(string $expected)
    {
        $this->assertSame($expected, $this->request());
    }

    public static function provideDataSets(): iterable
    {
        yield 'first' => ['first data set'];
    }

    public function testMissIsReportedEvenWhenCaught()
    {
        try {
            $this->request('https://example.com/missing?access_token=s3cr3t');
        } catch (TransportExceptionInterface) {
        }

        $this->addToAssertionCount(1);
    }

    private function request(string $url = 'https://example.com/'): string
    {
        $client = new RecorderHttpClient(new MockHttpClient(static fn () => throw new \LogicException('The network must not be reached.')), new HttpRecorder());

        return $client->request('GET', $url)->getContent();
    }
}
