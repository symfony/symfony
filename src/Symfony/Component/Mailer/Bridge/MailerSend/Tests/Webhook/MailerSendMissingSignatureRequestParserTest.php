<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailerSend\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\MailerSend\RemoteEvent\MailerSendPayloadConverter;
use Symfony\Component\Mailer\Bridge\MailerSend\Webhook\MailerSendRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class MailerSendMissingSignatureRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is required.');

        return new MailerSendRequestParser(new MailerSendPayloadConverter());
    }

    public static function getPayloads(): iterable
    {
        $filename = 'sent.json';
        $currentDir = \dirname((new \ReflectionClass(static::class))->getFileName());

        yield $filename => [
            file_get_contents($currentDir.'/Fixtures/sent.json'),
            include ($currentDir.'/Fixtures/sent.php'),
        ];
    }

    protected function getSecret(): string
    {
        return 'GvLY88Uyj70jQm3fUwYyWmAaiz98wWim';
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
        ], $payload);
    }
}
