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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Resend\RemoteEvent\ResendPayloadConverter;
use Symfony\Component\Mailer\Bridge\Resend\Webhook\ResendRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

#[Group('time-sensitive')]
class ResendRequestParserTest extends AbstractRequestParserTestCase
{
    public static function getStaleClockOffsets(): iterable
    {
        yield 'too old' => [301];
        yield 'too far in the future' => [-301];
    }

    #[DataProvider('getStaleClockOffsets')]
    public function testRejectSignedRequestWithStaleTimestamp(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/sent.json'));
        ClockMock::withClockMock((int) $request->headers->get('svix-timestamp') + $offset);

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
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/sent.json'));
        ClockMock::withClockMock((int) $request->headers->get('svix-timestamp') + $offset);

        $this->assertNotNull($this->createRequestParser()->parse($request, $this->getSecret()));
    }

    protected function createRequestParser(): RequestParserInterface
    {
        return new ResendRequestParser(new ResendPayloadConverter());
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
            'HTTP_svix-signature' => 'v1,4wjuRp64yC/2itgCQwl2xPePVwSPTdPbXLIY6IxGLTA=',
        ], str_replace("\n", "\r\n", $payload));
    }

    #[DataProvider('provideInvalidSignatureHeaders')]
    public function testRejectsInvalidSignatureHeader(string $signature)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/sent.json'));
        $request->headers->set('svix-signature', $signature);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('No signatures found matching the expected signature.');
        $this->createRequestParser()->parse($request, $this->getSecret());
    }

    public static function provideInvalidSignatureHeaders(): iterable
    {
        yield 'forged' => ['v1,AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='];
        yield 'empty' => [''];
        yield 'no version' => ['4wjuRp64yC/2itgCQwl2xPePVwSPTdPbXLIY6IxGLTA='];
        yield 'version only' => ['v1'];
        yield 'unknown version' => ['v2,4wjuRp64yC/2itgCQwl2xPePVwSPTdPbXLIY6IxGLTA='];
    }

    public function testRejectsMissingSignatureHeader()
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/sent.json'));
        $request->headers->remove('svix-signature');

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Request does not match.');
        $this->createRequestParser()->parse($request, $this->getSecret());
    }

    public function testRejectForgedSignatureBeforeParsingThePayload()
    {
        $request = $this->createRequest('1');
        $request->headers->set('svix-signature', 'v1,AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('No signatures found matching the expected signature.');

        $this->createRequestParser()->parse($request, $this->getSecret());
    }
}
