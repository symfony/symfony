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

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Scaleway\RemoteEvent\ScalewayPayloadConverter;
use Symfony\Component\Mailer\Bridge\Scaleway\Webhook\ScalewayRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class ScalewayRequestParserTest extends AbstractRequestParserTestCase
{
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
