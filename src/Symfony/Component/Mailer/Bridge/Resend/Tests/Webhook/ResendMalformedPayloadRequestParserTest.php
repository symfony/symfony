<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Resend\Tests\Webhook;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Resend\RemoteEvent\ResendPayloadConverter;
use Symfony\Component\Mailer\Bridge\Resend\Webhook\ResendRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

#[Group('time-sensitive')]
class ResendMalformedPayloadRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Payload is malformed.');

        return new ResendRequestParser(new ResendPayloadConverter());
    }

    public static function getPayloads(): iterable
    {
        yield 'missing-fields' => ['{}', []];
    }

    protected function getSecret(): string
    {
        return 'whsec_ESwTAuuIe3yfH4DgdgI+ENsiNzPAGdp+';
    }

    protected function createRequest(string $payload): Request
    {
        ClockMock::withClockMock(1712569389);

        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_svix-id' => '172c41ce-ba6d-4281-8a7a-541faa725748',
            'HTTP_svix-timestamp' => '1712569389',
            'HTTP_svix-signature' => 'v1,3LK1qunqNcqstAUfpz7z6mR8MmnaY879b8972Avqv3E=',
        ], $payload);
    }
}
