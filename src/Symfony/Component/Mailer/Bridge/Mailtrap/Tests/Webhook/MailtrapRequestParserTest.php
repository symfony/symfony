<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailtrap\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Mailtrap\RemoteEvent\MailtrapPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailtrap\Webhook\MailtrapRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class MailtrapRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new MailtrapRequestParser(new MailtrapPayloadConverter());
    }

    protected function getSecret(): string
    {
        return 'top-secret';
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_Mailtrap-Signature' => hash_hmac('sha256', $payload, 'top-secret'),
        ], $payload);
    }

    public function testRejectMissingSignature()
    {
        $parser = new MailtrapRequestParser(new MailtrapPayloadConverter());
        $payload = file_get_contents(__DIR__.'/Fixtures/delivery.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
        ], $payload);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is required.');
        $parser->parse($request, 'top-secret');
    }

    public function testRejectWrongSecret()
    {
        $parser = new MailtrapRequestParser(new MailtrapPayloadConverter());
        $payload = file_get_contents(__DIR__.'/Fixtures/delivery.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_Mailtrap-Signature' => hash_hmac('sha256', $payload, 'wrong-secret'),
        ], $payload);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong.');
        $parser->parse($request, 'top-secret');
    }
}
