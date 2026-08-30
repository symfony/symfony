<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Tests\Webhook;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Sendgrid\RemoteEvent\SendgridPayloadConverter;
use Symfony\Component\Mailer\Bridge\Sendgrid\Webhook\SendgridRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

#[Group('time-sensitive')]
class SendgridStaleTimestampRequestParserTest extends AbstractRequestParserTestCase
{
    public function testRejectSignedRequestFromTheFuture()
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/webhook.json'));
        ClockMock::withClockMock((int) $request->headers->get('X-Twilio-Email-Event-Webhook-Timestamp') - 301);

        $this->createRequestParser()->parse($request, $this->getSecret());
    }

    protected function createRequestParser(): RequestParserInterface
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Timestamp is outside the allowed time window.');

        return new SendgridRequestParser(new SendgridPayloadConverter());
    }

    protected function createRequest(string $payload): Request
    {
        ClockMock::withClockMock(1600112502 + 301);

        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_X-Twilio-Email-Event-Webhook-Signature' => 'MEUCIGHQVtGj+Y3LkG9fLcxf3qfI10QysgDWmMOVmxG0u6ZUAiEAyBiXDWzM+uOe5W0JuG+luQAbPIqHh89M15TluLtEZtM=',
            'HTTP_X-Twilio-Email-Event-Webhook-Timestamp' => '1600112502',
        ], str_replace("\n", "\r\n", $payload));
    }

    protected function getSecret(): string
    {
        return 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE83T4O/n84iotIvIW4mdBgQ/7dAfSmpqIM8kF9mN1flpVKS3GRqe62gw+2fNNRaINXvVpiglSI8eNEc6wEA3F+g==';
    }
}
