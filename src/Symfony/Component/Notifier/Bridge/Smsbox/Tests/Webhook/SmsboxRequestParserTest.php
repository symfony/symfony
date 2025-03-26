<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsbox\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Notifier\Bridge\Smsbox\Webhook\SmsboxRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class SmsboxRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new SmsboxRequestParser();
    }

    protected function createRequest(string $payload): Request
    {
        parse_str(trim($payload), $parameters);

        return Request::create('/', 'GET', $parameters, [], [], ['REMOTE_ADDR' => '37.59.198.135']);
    }

    protected static function getFixtureExtension(): string
    {
        return 'txt';
    }
}
