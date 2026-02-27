<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sweego\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Sweego\RemoteEvent\SweegoPayloadConverter;
use Symfony\Component\Mailer\Bridge\Sweego\Webhook\SweegoRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class SweegoSignedRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new SweegoRequestParser(new SweegoPayloadConverter());
    }

    public static function getPayloads(): iterable
    {
        $filename = 'delivered.json';
        $currentDir = \dirname((new \ReflectionClass(static::class))->getFileName());

        yield $filename => [
            file_get_contents($currentDir.'/Fixtures/delivered.json'),
            include ($currentDir.'/Fixtures/delivered.php'),
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
            'HTTP_webhook-id' => '9f26b9d0-13d7-410c-ba04-5019cd30e6d0',
            'HTTP_webhook-timestamp' => '1723737959',
            'HTTP_webhook-signature' => 'E1RfmN81xnbXMqDZUD0eJjPQEWmf24ft//gtV29bp18=',
        ], $payload);
    }
}
