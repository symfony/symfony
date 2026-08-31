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

use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Sendgrid\RemoteEvent\SendgridPayloadConverter;
use Symfony\Component\Mailer\Bridge\Sendgrid\Webhook\SendgridRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

/**
 * @author WoutervanderLoop.nl <info@woutervanderloop.nl>
 *
 * @requires extension openssl
 *
 * @group time-sensitive
 */
class SendgridSignedRequestParserTest extends AbstractRequestParserTestCase
{
    public static function getToleratedClockOffsets(): iterable
    {
        yield 'at the past edge' => [300];
        yield 'at the future edge' => [-300];
    }

    /**
     * @dataProvider getToleratedClockOffsets
     */
    public function testAcceptSignedRequestWithinTolerance(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/webhook.json'));
        ClockMock::withClockMock((int) $request->headers->get('X-Twilio-Email-Event-Webhook-Timestamp') + $offset);

        $this->assertNotNull($this->createRequestParser()->parse($request, $this->getSecret()));
    }

    public function testRejectUnsignedRequestBeforeParsingThePayload()
    {
        $request = $this->createRequest('{"not":"an event"}');
        $request->headers->remove('X-Twilio-Email-Event-Webhook-Signature');

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is required.');

        $this->createRequestParser()->parse($request, $this->getSecret());
    }

    public function testRejectForgedSignatureBeforeParsingThePayload()
    {
        $request = $this->createRequest('{"not":"an event"}');

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong.');

        $this->createRequestParser()->parse($request, $this->getSecret());
    }

    protected function createRequestParser(): RequestParserInterface
    {
        return new SendgridRequestParser(new SendgridPayloadConverter(), true);
    }

    /**
     * @see https://github.com/sendgrid/sendgrid-php/blob/9335dca98bc64456a72db73469d1dd67db72f6ea/test/unit/EventWebhookTest.php#L20
     */
    protected function createRequest(string $payload): Request
    {
        ClockMock::withClockMock(1600112502);

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
