<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailjet\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Mailjet\RemoteEvent\MailjetPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailjet\Webhook\MailjetRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class MailjetRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new MailjetRequestParser(new MailjetPayloadConverter());
    }

    protected function getSecret(): string
    {
        return ':top-secret';
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_Authorization' => 'Basic '.base64_encode(':top-secret'),
        ], $payload);
    }

    public function testRejectMissingCredentials()
    {
        $parser = new MailjetRequestParser(new MailjetPayloadConverter());
        $payload = file_get_contents(__DIR__.'/Fixtures/sent.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
        ], $payload);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid credentials.');
        $parser->parse($request, ':top-secret');
    }

    public function testRejectWrongSecret()
    {
        $parser = new MailjetRequestParser(new MailjetPayloadConverter());
        $payload = file_get_contents(__DIR__.'/Fixtures/sent.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_Authorization' => 'Basic '.base64_encode(':wrong-secret'),
        ], $payload);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid credentials.');
        $parser->parse($request, ':top-secret');
    }
}
