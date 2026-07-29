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
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class SweegoRequestParserTest extends AbstractRequestParserTestCase
{
    private const WEBHOOK_ID = 'a5ccc627-6e43-4012-bb29-f1bfe3a3d13e';
    private const WEBHOOK_TIMESTAMP = '1725290740';

    protected function createRequestParser(): RequestParserInterface
    {
        return new SweegoRequestParser();
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_webhook-id' => self::WEBHOOK_ID,
            'HTTP_webhook-timestamp' => self::WEBHOOK_TIMESTAMP,
            'HTTP_webhook-signature' => base64_encode(hash_hmac('sha256', \sprintf('%s.%s.%s', self::WEBHOOK_ID, self::WEBHOOK_TIMESTAMP, $payload), '', true)),
        ], $payload);
    }
}
