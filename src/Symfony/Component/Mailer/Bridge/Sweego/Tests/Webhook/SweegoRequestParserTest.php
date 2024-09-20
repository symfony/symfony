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

class SweegoRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new SweegoRequestParser(new SweegoPayloadConverter());
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
        ], $payload);
    }
}
