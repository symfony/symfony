<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sweego\Tests\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Sweego\RemoteEvent\SweegoPayloadConverter;
use Symfony\Component\Mailer\Bridge\Sweego\Webhook\SweegoRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

#[Group('time-sensitive')]
class SweegoSignedRequestParserTest extends AbstractRequestParserTestCase
{
    public static function getPayloads(): iterable
    {
        $filename = 'delivered.json';
        $currentDir = \dirname((new \ReflectionClass(static::class))->getFileName());

        yield $filename => [
            file_get_contents($currentDir.'/Fixtures/delivered.json'),
            include ($currentDir.'/Fixtures/delivered.php'),
        ];
    }

    public static function getStaleClockOffsets(): iterable
    {
        yield 'too old' => [301];
        yield 'too far in the future' => [-301];
    }

    #[DataProvider('getStaleClockOffsets')]
    public function testRejectSignedRequestWithStaleTimestamp(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        ClockMock::withClockMock((int) $request->headers->get('webhook-timestamp') + $offset);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Timestamp is outside the allowed time window.');

        $this->createRequestParser()->parse($request, $this->getSecret());
    }

    public static function getToleratedClockOffsets(): iterable
    {
        yield 'at the past edge' => [300];
        yield 'at the future edge' => [-300];
    }

    #[DataProvider('getToleratedClockOffsets')]
    public function testAcceptSignedRequestWithinTolerance(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        ClockMock::withClockMock((int) $request->headers->get('webhook-timestamp') + $offset);

        $this->assertNotNull($this->createRequestParser()->parse($request, $this->getSecret()));
    }

    protected function createRequestParser(): RequestParserInterface
    {
        return new SweegoRequestParser(new SweegoPayloadConverter());
    }

    protected function getSecret(): string
    {
        return 'GvLY88Uyj70jQm3fUwYyWmAaiz98wWim';
    }

    protected function createRequest(string $payload): Request
    {
        ClockMock::withClockMock(1723737959);

        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_webhook-id' => '9f26b9d0-13d7-410c-ba04-5019cd30e6d0',
            'HTTP_webhook-timestamp' => '1723737959',
            'HTTP_webhook-signature' => 'E1RfmN81xnbXMqDZUD0eJjPQEWmf24ft//gtV29bp18=',
        ], $payload);
    }
}
