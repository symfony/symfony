<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Scaleway\Tests\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Scaleway\RemoteEvent\ScalewayPayloadConverter;
use Symfony\Component\Mailer\Bridge\Scaleway\Webhook\ScalewayRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

#[Group('time-sensitive')]
class ScalewayRequestParserTest extends AbstractRequestParserTestCase
{
    public static function getStaleClockOffsets(): iterable
    {
        yield 'too old' => [28801];
        yield 'too far in the future' => [-28801];
    }

    #[DataProvider('getStaleClockOffsets')]
    public function testRejectSignedRequestWithStaleTimestamp(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/email_delivered.json'));
        ClockMock::withClockMock(1768473001 + $offset);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Timestamp is outside the allowed time window.');

        $this->createRequestParser()->parse($request, $this->getSecret());
    }

    public static function getToleratedClockOffsets(): iterable
    {
        yield 'at the past edge' => [28800];
        yield 'at the future edge' => [-28800];
    }

    #[DataProvider('getToleratedClockOffsets')]
    public function testAcceptSignedRequestWithinTolerance(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/email_delivered.json'));
        ClockMock::withClockMock(1768473001 + $offset);

        $this->assertNotNull($this->createRequestParser()->parse($request, $this->getSecret()));
    }

    protected function createRequestParser(): RequestParserInterface
    {
        return new ScalewayRequestParser(
            new ScalewayPayloadConverter(),
            new MockHttpClient(static fn () => new MockResponse(file_get_contents(__DIR__.'/Fixtures/signing.crt'))),
            null,
            __DIR__.'/Fixtures/trust-chain.pem',
        );
    }

    protected function createRequest(string $payload): Request
    {
        ClockMock::withClockMock(1768473001);

        $envelope = [
            'Type' => 'Notification',
            'MessageId' => '9ae5c56c-6c9c-42e5-b0b1-0fe0f8bbdbf7',
            'TopicArn' => 'arn:scw:sns:fr-par:project-8c8bfa06:mailer-events',
            'Message' => $payload,
            'Timestamp' => '2026-01-15T10:30:01.000Z',
            'SignatureVersion' => '2',
            'SigningCertURL' => 'https://messaging.s3.fr-par.scw.cloud/certs/cert-11111111.pem',
        ];

        $signedString = '';
        foreach (['Message', 'MessageId', 'Timestamp', 'TopicArn', 'Type'] as $key) {
            $signedString .= $key."\n".$envelope[$key]."\n";
        }
        openssl_sign($signedString, $signature, file_get_contents(__DIR__.'/Fixtures/signing.key'), \OPENSSL_ALGO_SHA256);
        $envelope['Signature'] = base64_encode($signature);

        return Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain; charset=UTF-8'], json_encode($envelope));
    }
}
