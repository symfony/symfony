<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsbox\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Notifier\Bridge\Smsbox\Webhook\SmsboxRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class SmsboxRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new SmsboxRequestParser();
    }

    protected function createRequest(string $payload): Request
    {
        parse_str(trim($payload), $parameters);

        return Request::create('/', 'GET', $parameters, [], [], ['REMOTE_ADDR' => '37.59.198.135']);
    }

    protected static function getFixtureExtension(): string
    {
        return 'txt';
    }

    public function testProviderIpsExposed()
    {
        $this->assertContains('37.59.198.135', SmsboxRequestParser::PROVIDER_IPS);
        $this->assertNotContains('127.0.0.1', SmsboxRequestParser::PROVIDER_IPS);
    }

    public function testDefaultRejectsUnknownIp()
    {
        $payload = file_get_contents(__DIR__.'/Fixtures/delivred.txt');
        parse_str(trim($payload), $parameters);
        $request = Request::create('/', 'GET', $parameters, [], [], ['REMOTE_ADDR' => '198.51.100.7']);

        $this->expectException(RejectWebhookException::class);
        (new SmsboxRequestParser())->parse($request, '');
    }

    public function testAllowedIpsConstructorOverride()
    {
        $payload = file_get_contents(__DIR__.'/Fixtures/delivred.txt');
        parse_str(trim($payload), $parameters);
        $request = Request::create('/', 'GET', $parameters, [], [], ['REMOTE_ADDR' => '198.51.100.7']);

        $parser = new SmsboxRequestParser(['198.51.100.7']);
        $this->assertNotNull($parser->parse($request, ''));
    }
}
