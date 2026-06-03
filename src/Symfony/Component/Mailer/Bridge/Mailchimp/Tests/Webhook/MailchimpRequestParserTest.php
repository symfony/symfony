<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Mailchimp\RemoteEvent\MailchimpPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailchimp\Webhook\MailchimpRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class MailchimpRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new MailchimpRequestParser(new MailchimpPayloadConverter());
    }

    protected function createRequest(string $payload): Request
    {
        $decodedPayload = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        $mandrillSignature = $decodedPayload['X-Mandrill-Signature'] ?? '';
        $formData = ['mandrill_events' => json_encode($decodedPayload['mandrill_events'], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)];

        $request = Request::create('/', 'POST', $formData, [], [], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
        $request->headers->set('X-Mandrill-Signature', $mandrillSignature);

        return $request;
    }

    protected function getSecret(): string
    {
        return 'key-0p6mqbf74lb20gzq9f4dhpn9rg3zyk26';
    }

    public function testEmptyWebhookCheck()
    {
        $request = Request::create('/', 'POST', ['mandrill_events' => '[]'], [], [], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
        // Mailchimp signs empty-check requests with the literal secret 'test-webhook'
        $request->headers->set('X-Mandrill-Signature', 'QANhYzyeonX51wyzKImgnLZ27CE=');

        $parser = $this->createRequestParser();

        $this->assertNull($parser->parse($request, $this->getSecret()));
    }
}
