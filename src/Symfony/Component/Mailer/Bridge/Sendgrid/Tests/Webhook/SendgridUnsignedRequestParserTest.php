<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Sendgrid\RemoteEvent\SendgridPayloadConverter;
use Symfony\Component\Mailer\Bridge\Sendgrid\Webhook\SendgridRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

/**
 * @author WoutervanderLoop.nl <info@woutervanderloop.nl>
 */
class SendgridUnsignedRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new SendgridRequestParser(new SendgridPayloadConverter());
    }

    /**
     * @see https://github.com/sendgrid/sendgrid-php/blob/9335dca98bc64456a72db73469d1dd67db72f6ea/test/unit/EventWebhookTest.php#L20
     */
    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
        ], str_replace("\n", "\r\n", $payload));
    }
}
