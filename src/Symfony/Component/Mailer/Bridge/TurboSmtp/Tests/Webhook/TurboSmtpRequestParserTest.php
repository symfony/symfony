<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\TurboSmtp\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\TurboSmtp\RemoteEvent\TurboSmtpPayloadConverter;
use Symfony\Component\Mailer\Bridge\TurboSmtp\Webhook\TurboSmtpRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class TurboSmtpRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new TurboSmtpRequestParser(new TurboSmtpPayloadConverter());
    }

    protected function getSecret(): string
    {
        return 'symfony:top-secret';
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_Authorization' => 'Basic '.base64_encode('symfony:top-secret'),
        ], $payload);
    }

    public function testRejectMissingCredentials()
    {
        $parser = new TurboSmtpRequestParser(new TurboSmtpPayloadConverter());
        $payload = file_get_contents(__DIR__.'/Fixtures/delivered.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
        ], $payload);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid credentials.');
        $parser->parse($request, 'symfony:top-secret');
    }

    public function testRejectWrongSecret()
    {
        $parser = new TurboSmtpRequestParser(new TurboSmtpPayloadConverter());
        $payload = file_get_contents(__DIR__.'/Fixtures/delivered.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_Authorization' => 'Basic '.base64_encode('symfony:wrong-secret'),
        ], $payload);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid credentials.');
        $parser->parse($request, 'symfony:top-secret');
    }
}
