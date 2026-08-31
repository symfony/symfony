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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\AhaSend\RemoteEvent\AhaSendPayloadConverter;
use Symfony\Component\Mailer\Bridge\AhaSend\Webhook\AhaSendRequestParser;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

#[Group('time-sensitive')]
class AhaSendRequestParserTest extends AbstractRequestParserTestCase
{
    private const SECRET = 'nxLe:L:fZLb7J_Wb3uFeWX/&z4Ed#9&DxPL%Ud&:jhpAW1gLaR%AEFwfKnwp60cC';

    public static function getStaleClockOffsets(): iterable
    {
        yield 'too old' => [301];
        yield 'too far in the future' => [-301];
    }

    #[DataProvider('getStaleClockOffsets')]
    public function testRejectSignedRequestWithStaleTimestamp(int $offset)
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        ClockMock::withClockMock((int) $request->headers->get('webhook-timestamp') + $offset);

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Timestamp is outside the allowed time window.');

        $this->createRequestParser()->parse($request, self::SECRET);
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
        ClockMock::withClockMock((int) $request->headers->get('webhook-timestamp') + $offset);

        $this->assertNotNull($this->createRequestParser()->parse($request, self::SECRET));
    }

    public function testRequestSignedWithAnEmptySecretIsRejected()
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        $signedPayload = $request->headers->get('webhook-id').'.'.$request->headers->get('webhook-timestamp').'.'.$request->getContent();
        $request->headers->set('webhook-signature', 'v1,'.base64_encode(hash_hmac('sha256', $signedPayload, '', true)));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A non-empty secret is required.');

        $this->createRequestParser()->parse($request, '');
    }

    protected function createRequestParser(): RequestParserInterface
    {
        return new AhaSendRequestParser(new AhaSendPayloadConverter());
    }

    protected function createRequest(string $payload): Request
    {
        $payloadArray = json_decode($payload, true);

        $currentDir = \dirname((new \ReflectionClass(static::class))->getFileName());
        $type = str_replace('message.', '', $payloadArray['type']);
        $headers = file_get_contents($currentDir.'/Fixtures/'.$type.'_headers.txt');
        $server = [
            'Content-Type' => 'application/json',
        ];
        foreach (explode("\n", $headers) as $row) {
            $header = explode(':', $row);
            if (2 == \count($header)) {
                $server['HTTP_'.$header[0]] = $header[1];
            }
        }
        $payload = json_encode($payloadArray, \JSON_UNESCAPED_SLASHES);

        ClockMock::withClockMock((int) $server['HTTP_webhook-timestamp']);

        return Request::create('/', 'POST', [], [], [], $server, $payload);
    }

    protected function getSecret(): string
    {
        return self::SECRET;
    }

    public function testRejectForgedSignatureBeforeParsingThePayload()
    {
        $request = $this->createRequest(file_get_contents(__DIR__.'/Fixtures/delivered.json'));
        $request = Request::create('/', 'POST', [], [], [], $request->server->all(), '1');

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Invalid signature');

        $this->createRequestParser()->parse($request, $this->getSecret());
    }
}
