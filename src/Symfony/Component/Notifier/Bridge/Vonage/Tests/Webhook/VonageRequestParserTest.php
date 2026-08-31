<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Vonage\Tests\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Notifier\Bridge\Vonage\Webhook\VonageRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

#[Group('time-sensitive')]
class VonageRequestParserTest extends AbstractRequestParserTestCase
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
        ClockMock::withClockMock(self::getIssuedAt($request) + $offset);

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
        ClockMock::withClockMock(self::getIssuedAt($request) + $offset);

        $this->assertNotNull($this->createRequestParser()->parse($request, $this->getSecret()));
    }

    public function testMissingAuthorizationTokenThrows()
    {
        $request = $this->createRequest('{}');
        $request->headers->remove('Authorization');
        $parser = $this->createRequestParser();

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Missing "Authorization" header');

        $parser->parse($request, $this->getSecret());
    }

    public function testInvalidAuthorizationTokenThrows()
    {
        $request = $this->createRequest('{}');
        $request->headers->set('Authorization', 'Invalid Header');
        $parser = $this->createRequestParser();

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is wrong');

        $parser->parse($request, $this->getSecret());
    }

    public function testTamperedPayloadThrows()
    {
        $request = $this->createRequest('{"status":"delivered","message_uuid":"1","to":"447700900000","channel":"sms"}');
        $token = substr($request->headers->get('Authorization'), \strlen('Bearer '));
        $request = parent::createRequest('{"status":"rejected","message_uuid":"1","to":"447700900000","channel":"sms"}');
        $request->headers->set('Authorization', 'Bearer '.$token);
        $parser = $this->createRequestParser();

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Payload hash is wrong');

        $parser->parse($request, $this->getSecret());
    }

    public function testMissingPayloadHashThrows()
    {
        $request = $this->createRequest('{"status":"delivered","message_uuid":"1","to":"447700900000","channel":"sms"}');
        $request->headers->set('Authorization', 'Bearer '.$this->createJwt([]));
        $parser = $this->createRequestParser();

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Payload hash is wrong');

        $parser->parse($request, $this->getSecret());
    }

    protected function createRequestParser(): RequestParserInterface
    {
        return new VonageRequestParser();
    }

    protected function createRequest(string $payload): Request
    {
        $request = parent::createRequest($payload);
        $request->headers->set('Authorization', 'Bearer '.$this->createJwt(['payload_hash' => hash('sha256', $payload)]));
        ClockMock::withClockMock(self::getIssuedAt($request));

        return $request;
    }

    private function createJwt(array $claims): string
    {
        $encode = static fn (string $data) => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $jwt = $encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])).'.'.$encode(json_encode($claims + ['iat' => 1516239022, 'jti' => 'jti', 'iss' => 'Vonage']));

        return $jwt.'.'.$encode(hash_hmac('sha256', $jwt, $this->getSecret(), true));
    }

    protected function getSecret(): string
    {
        return 'secret-key';
    }

    private static function getIssuedAt(Request $request): int
    {
        $claims = explode('.', substr($request->headers->get('Authorization'), \strlen('Bearer ')))[1];

        return json_decode(base64_decode(strtr($claims, '-_', '+/')), true)['iat'];
    }
}
