<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Brevo\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Brevo\RemoteEvent\BrevoPayloadConverter;
use Symfony\Component\Mailer\Bridge\Brevo\Webhook\BrevoRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class BrevoRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new BrevoRequestParser(new BrevoPayloadConverter());
    }

    protected function getSecret(): string
    {
        return ':top-secret';
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'REMOTE_ADDR' => '1.179.112.5',
            'HTTP_Authorization' => 'Basic '.base64_encode(':top-secret'),
        ], $payload);
    }

    public function testProviderIpsExcludesLocalhost()
    {
        $this->assertNotContains('127.0.0.1', BrevoRequestParser::PROVIDER_IPS);
    }

    public function testDefaultRejectsLocalhost()
    {
        $parser = new BrevoRequestParser(new BrevoPayloadConverter());
        $payload = file_get_contents(__DIR__.'/Fixtures/hard_bounce.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_Authorization' => 'Basic '.base64_encode(':top-secret'),
        ], $payload);

        $this->expectException(RejectWebhookException::class);
        $parser->parse($request, ':top-secret');
    }

    public function testAcceptsExplicitlyAllowedIp()
    {
        $parser = new BrevoRequestParser(new BrevoPayloadConverter(), ['127.0.0.1']);
        $payload = file_get_contents(__DIR__.'/Fixtures/hard_bounce.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_Authorization' => 'Basic '.base64_encode(':top-secret'),
        ], $payload);

        $this->assertNotNull($parser->parse($request, ':top-secret'));
    }

    public function testRejectMissingCredentials()
    {
        $parser = new BrevoRequestParser(new BrevoPayloadConverter());
        $payload = file_get_contents(__DIR__.'/Fixtures/hard_bounce.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'REMOTE_ADDR' => '1.179.112.5',
        ], $payload);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid credentials.');
        $parser->parse($request, ':top-secret');
    }

    public function testRejectWrongSecret()
    {
        $parser = new BrevoRequestParser(new BrevoPayloadConverter());
        $payload = file_get_contents(__DIR__.'/Fixtures/hard_bounce.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'REMOTE_ADDR' => '1.179.112.5',
            'HTTP_Authorization' => 'Basic '.base64_encode(':wrong-secret'),
        ], $payload);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid credentials.');
        $parser->parse($request, ':top-secret');
    }
}
