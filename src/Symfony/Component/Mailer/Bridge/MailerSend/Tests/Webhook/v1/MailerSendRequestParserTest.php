<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailerSend\Tests\Webhook\v1;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\MailerSend\RemoteEvent\MailerSendPayloadConverter;
use Symfony\Component\Mailer\Bridge\MailerSend\Webhook\MailerSendRequestParser;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class MailerSendRequestParserTest extends AbstractRequestParserTestCase
{
    private const SECRET = 'GvLY88Uyj70jQm3fUwYyWmAaiz98wWim';

    public function testWebhookTest()
    {
        $payload = json_encode([
            'type' => 'webhook.test',
            'message' => 'This is a ping test message',
            'created_at' => '2026-04-08T12:19:27.608339Z',
        ]);

        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
        ], $payload);
        $request->headers->set('Signature', '7fb29be5b1cdb588ee67fa43a0a9d1b394cbb9603a0ac0b87924649d715194c2');

        $parser = $this->createRequestParser();

        try {
            $parser->parse($request, MailerSendRequestParser::TEST_SECRET);
        } catch (RejectWebhookException $e) {
            $this->assertSame(202, $e->getStatusCode());
        }
    }

    public function testWebhookTestIsAcceptedWithoutConfiguredSecret()
    {
        $payload = json_encode([
            'type' => 'webhook.test',
            'message' => 'This is a ping test message',
            'created_at' => '2026-04-08T12:19:27.608339Z',
        ]);

        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_Signature' => '7fb29be5b1cdb588ee67fa43a0a9d1b394cbb9603a0ac0b87924649d715194c2',
        ], $payload);

        try {
            $this->createRequestParser()->parse($request, '');
            $this->fail('A RejectWebhookException with a 202 status code should have been thrown.');
        } catch (RejectWebhookException $e) {
            $this->assertSame(202, $e->getStatusCode());
        }
    }

    public function testRequestSignedWithAnEmptySecretIsRejected()
    {
        $payload = file_get_contents(__DIR__.'/Fixtures/sent.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_Signature' => 'f09863b0c747482e3ea5d3784bb51e71b60a4126d7ffd20397fa71f22f240de1',
        ], $payload);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A non-empty secret is required.');

        $this->createRequestParser()->parse($request, '');
    }

    protected function createRequestParser(): RequestParserInterface
    {
        return new MailerSendRequestParser(new MailerSendPayloadConverter());
    }

    protected function getSecret(): string
    {
        return self::SECRET;
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_Signature' => hash_hmac('sha256', $payload, self::SECRET),
        ], $payload);
    }
}
