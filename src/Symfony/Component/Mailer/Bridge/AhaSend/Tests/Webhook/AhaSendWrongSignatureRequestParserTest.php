<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\AhaSend\Tests\Webhook;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\AhaSend\RemoteEvent\AhaSendPayloadConverter;
use Symfony\Component\Mailer\Bridge\AhaSend\Webhook\AhaSendRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

#[Group('time-sensitive')]
class AhaSendWrongSignatureRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid signature');

        return new AhaSendRequestParser(new AhaSendPayloadConverter());
    }

    public static function getPayloads(): iterable
    {
        $payload = '{"type":"message.delivered","timestamp":"2024-01-01T00:00:00Z","data":{}}';

        yield 'forged signature' => [$payload, []];
        yield 'signed with another secret' => [$payload, []];
    }

    protected function getSecret(): string
    {
        return 'nxLe:L:fZLb7J_Wb3uFeWX/&z4Ed#9&DxPL%Ud&:jhpAW1gLaR%AEFwfKnwp60cC';
    }

    protected function createRequest(string $payload): Request
    {
        $signature = 'forged signature' === $this->dataName() ? 'v1,'.base64_encode(random_bytes(32)) : 'v1,'.base64_encode(hash_hmac('sha256', "id.1730057850.$payload", 'another-secret', true));

        ClockMock::withClockMock(1730057850);

        return Request::create('/', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_webhook-id' => 'id',
            'HTTP_webhook-timestamp' => '1730057850',
            'HTTP_webhook-signature' => $signature,
        ], $payload);
    }
}
