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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Sweego\RemoteEvent\SweegoPayloadConverter;
use Symfony\Component\Mailer\Bridge\Sweego\Webhook\SweegoRequestParser;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class SweegoRequestParserTest extends AbstractRequestParserTestCase
{
    private const SECRET = 'GvLY88Uyj70jQm3fUwYyWmAaiz98wWim';
    private const WEBHOOK_ID = '9f26b9d0-13d7-410c-ba04-5019cd30e6d0';

    public function testRequestSignedWithAnEmptySecretIsRejected()
    {
        $payload = file_get_contents(__DIR__.'/Fixtures/delivered.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_webhook-id' => self::WEBHOOK_ID,
            'HTTP_webhook-timestamp' => '1723737959',
            'HTTP_webhook-signature' => 'uN8Pj2+RzSIzQh/FCnOLEmE40qcRFGuPuenm1t0DPk8=',
        ], $payload);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A non-empty secret is required.');

        $this->createRequestParser()->parse($request, '');
    }

    protected function createRequestParser(): RequestParserInterface
    {
        return new SweegoRequestParser(new SweegoPayloadConverter());
    }

    protected function getSecret(): string
    {
        return self::SECRET;
    }

    protected function createRequest(string $payload): Request
    {
        $timestamp = (string) time();
        $contentToSign = \sprintf('%s.%s.%s', self::WEBHOOK_ID, $timestamp, $payload);

        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_webhook-id' => self::WEBHOOK_ID,
            'HTTP_webhook-timestamp' => $timestamp,
            'HTTP_webhook-signature' => base64_encode(hash_hmac('sha256', $contentToSign, base64_decode(self::SECRET), true)),
        ], $payload);
    }

    public function testRejectForgedSignatureBeforeParsingThePayload()
    {
        $request = $this->createRequest('1');
        $request->headers->set('webhook-signature', 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid signature.');

        $this->createRequestParser()->parse($request, $this->getSecret());
    }
}
