<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sweego\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Notifier\Bridge\Sweego\Webhook\SweegoRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class SweegoWrongSignatureRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid signature.');

        return new SweegoRequestParser();
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_webhook-id' => 'a5ccc627-6e43-4012-bb29-f1bfe3a3d13e',
            'HTTP_webhook-timestamp' => '1725290740',
            'HTTP_webhook-signature' => 'wrong_signature',
        ], $payload);
    }
}
