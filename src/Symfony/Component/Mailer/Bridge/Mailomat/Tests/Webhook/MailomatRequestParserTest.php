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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Mailomat\RemoteEvent\MailomatPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailomat\Webhook\MailomatRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

#[Group('time-sensitive')]
class MailomatRequestParserTest extends AbstractRequestParserTestCase
{
    public static function getStaleClockOffsets(): iterable
    {
        yield 'too old' => [301];
        yield 'too far in the future' => [-301];
    }

    #[DataProvider('getStaleClockOffsets')]
    public function testRejectSignedRequestWithStaleTimestamp(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        ClockMock::withClockMock((int) $request->headers->get('X-MOM-Webhook-Timestamp') + $offset);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Timestamp is outside the allowed time window.');

        $this->createRequestParser()->parse($request, $this->getSecret());
    }

    public static function getToleratedClockOffsets(): iterable
    {
        yield 'at the past edge' => [300];
        yield 'at the future edge' => [-300];
    }

    #[DataProvider('getToleratedClockOffsets')]
    public function testAcceptSignedRequestWithinTolerance(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        ClockMock::withClockMock((int) $request->headers->get('X-MOM-Webhook-Timestamp') + $offset);

        $this->assertNotNull($this->createRequestParser()->parse($request, $this->getSecret()));
    }

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
        ClockMock::withClockMock(1718004211);

        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_X-MOM-Webhook-Event' => 'delivered',
            'HTTP_X-MOM-Webhook-ID' => '1d958822-0934-4c6a-abc8-5defec4baa64',
            'HTTP_X-MOM-Webhook-Signature' => 'sha256=1a1e3be272212aefe668db51231f54ba66759d6d4b9c5e03d4aa6825f8eb157c',
            'HTTP_X-MOM-Webhook-Timestamp' => '1718004211',
        ], str_replace("\n", "\r\n", $payload));
    }

    public function testRejectsForgedSignature()
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        $request->headers->set('X-MOM-Webhook-Signature', 'sha256='.hash_hmac('sha256', '1d958822-0934-4c6a-abc8-5defec4baa64.delivered.1718004211', 'another-secret'));

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong.');

        $this->createRequestParser()->parse($request, $this->getSecret());
    }

    public function testRejectsMissingSignature()
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        $request->headers->remove('X-MOM-Webhook-Signature');

        $this->expectException(RejectWebhookException::class);

        $this->createRequestParser()->parse($request, $this->getSecret());
    }

    #[DataProvider('provideNonSha256Algorithms')]
    public function testRejectsNonSha256Algorithm(string $algo)
    {
        $secret = $this->getSecret();
        $data = '1d958822-0934-4c6a-abc8-5defec4baa64.delivered.1718004211';
        $payload = file_get_contents(__DIR__.'/Fixtures/delivered.json');

        ClockMock::withClockMock(1718004211);
        $request = Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_X-MOM-Webhook-Event' => 'delivered',
            'HTTP_X-MOM-Webhook-ID' => '1d958822-0934-4c6a-abc8-5defec4baa64',
            'HTTP_X-MOM-Webhook-Signature' => $algo.'='.hash_hmac($algo, $data, $secret),
            'HTTP_X-MOM-Webhook-Timestamp' => '1718004211',
        ], str_replace("\n", "\r\n", $payload));

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong.');

        $this->createRequestParser()->parse($request, $secret);
    }

    public static function provideNonSha256Algorithms(): iterable
    {
        yield 'md5' => ['md5'];
        yield 'sha1' => ['sha1'];
        yield 'sha512' => ['sha512'];
    }

    public function testRejectForgedSignatureBeforeParsingThePayload()
    {
        $request = $this->createRequest('1');
        $request->headers->set('X-MOM-Webhook-Signature', 'sha256='.hash_hmac('sha256', 'forged', 'another-secret'));

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong.');

        $this->createRequestParser()->parse($request, $this->getSecret());
    }
}
