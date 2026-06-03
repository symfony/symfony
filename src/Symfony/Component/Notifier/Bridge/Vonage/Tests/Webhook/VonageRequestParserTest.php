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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Notifier\Bridge\Vonage\Webhook\VonageRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class VonageRequestParserTest extends AbstractRequestParserTestCase
{
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

        return $request;
    }

    protected function getSecret(): string
    {
        return 'secret-key';
    }
}
