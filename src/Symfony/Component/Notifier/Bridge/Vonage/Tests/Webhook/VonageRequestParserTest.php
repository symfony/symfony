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

use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Notifier\Bridge\Vonage\Webhook\VonageRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

/**
 * @group time-sensitive
 */
class VonageRequestParserTest extends AbstractRequestParserTestCase
{
    public static function getStaleClockOffsets(): iterable
    {
        yield 'too old' => [301];
        yield 'too far in the future' => [-301];
    }

    /**
     * @dataProvider getStaleClockOffsets
     */
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

    /**
     * @dataProvider getToleratedClockOffsets
     */
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

    protected function createRequestParser(): RequestParserInterface
    {
        return new VonageRequestParser();
    }

    protected function createRequest(string $payload): Request
    {
        // JWT Token signed with the secret key
        $jwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.kK9JnTXZwzNo3BYNXJT57PGLnQk-Xyu7IBhRWFmc4C0';

        $request = parent::createRequest($payload);
        $request->headers->set('Authorization', 'Bearer '.$jwt);
        ClockMock::withClockMock(self::getIssuedAt($request));

        return $request;
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
