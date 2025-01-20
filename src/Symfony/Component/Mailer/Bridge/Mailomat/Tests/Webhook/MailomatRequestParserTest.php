<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailomat\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Mailomat\RemoteEvent\MailomatPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailomat\Webhook\MailomatRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class MailomatRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new MailomatRequestParser(new MailomatPayloadConverter());
    }

    protected function getSecret(): string
    {
        return 'NgD3IyUA0oLfkM5IyL8tdMNJeIYeBXOpAcnulN1du1aqh3jFbo766lKdJvMePUy5';
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_X-MOM-Webhook-Event' => 'delivered',
            'HTTP_X-MOM-Webhook-ID' => '1d958822-0934-4c6a-abc8-5defec4baa64',
            'HTTP_X-MOM-Webhook-Signature' => 'sha256=1a1e3be272212aefe668db51231f54ba66759d6d4b9c5e03d4aa6825f8eb157c',
            'HTTP_X-MOM-Webhook-Timestamp' => '1718004211',
        ], str_replace("\n", "\r\n", $payload));
    }
}
