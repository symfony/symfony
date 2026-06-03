<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Postmark\RemoteEvent\PostmarkPayloadConverter;
use Symfony\Component\Mailer\Bridge\Postmark\Webhook\PostmarkRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class PostmarkRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new PostmarkRequestParser(new PostmarkPayloadConverter());
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'REMOTE_ADDR' => '3.134.147.250',
        ], $payload);
    }

    public function testProviderIpsExcludesLocalhost()
    {
        $this->assertNotContains('127.0.0.1', PostmarkRequestParser::PROVIDER_IPS);
    }

    public function testDefaultRejectsLocalhost()
    {
        $parser = new PostmarkRequestParser(new PostmarkPayloadConverter());
        $payload = file_get_contents(__DIR__.'/Fixtures/delivery.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.1',
        ], $payload);

        $this->expectException(RejectWebhookException::class);
        $parser->parse($request, '');
    }

    public function testAcceptsExplicitlyAllowedIp()
    {
        $parser = new PostmarkRequestParser(new PostmarkPayloadConverter(), ['127.0.0.1']);
        $payload = file_get_contents(__DIR__.'/Fixtures/delivery.json');
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.1',
        ], $payload);

        $this->assertNotNull($parser->parse($request, ''));
    }
}
