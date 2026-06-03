<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailerSend\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\MailerSend\RemoteEvent\MailerSendPayloadConverter;
use Symfony\Component\Mailer\Bridge\MailerSend\Webhook\MailerSendRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class MailerSendRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new MailerSendRequestParser(new MailerSendPayloadConverter());
    }

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
}
