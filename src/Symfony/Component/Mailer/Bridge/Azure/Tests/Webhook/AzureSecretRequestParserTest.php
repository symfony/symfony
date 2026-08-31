<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Azure\Tests\Webhook;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Azure\RemoteEvent\AzurePayloadConverter;
use Symfony\Component\Mailer\Bridge\Azure\Webhook\AzureRequestParser;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

class AzureSecretRequestParserTest extends TestCase
{
    public function testRequestWithoutTheConfiguredSecretIsRejected()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid secret.');

        $this->parse(Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '[]'), 'the-secret');
    }

    public function testAnEmptySecretIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A non-empty secret is required.');

        $this->parse(Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '[]'), '');
    }

    public function testRequestWithAWrongSecretIsRejected()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid secret.');

        $this->parse(Request::create('/?secret=nope', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '[]'), 'the-secret');
    }

    public function testRequestWithTheConfiguredSecretIsAccepted()
    {
        $events = $this->parse(Request::create('/?secret=the-secret', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $this->deliveryPayload()), 'the-secret');

        $this->assertCount(1, $events);
    }

    private function deliveryPayload(): string
    {
        return file_get_contents(__DIR__.'/Fixtures/delivery.json');
    }

    private function parse(Request $request, string $secret): array
    {
        return (new AzureRequestParser(new AzurePayloadConverter()))->parse($request, $secret);
    }
}
