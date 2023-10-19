<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Twilio\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Notifier\Bridge\Twilio\Webhook\TwilioRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class TwilioRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new TwilioRequestParser();
    }

    protected function createRequest(string $payload): Request
    {
        parse_str(trim($payload), $parameters);

        return Request::create('/', 'POST', $parameters, [], [], [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
    }

    protected static function getFixtureExtension(): string
    {
        return 'txt';
    }
}
