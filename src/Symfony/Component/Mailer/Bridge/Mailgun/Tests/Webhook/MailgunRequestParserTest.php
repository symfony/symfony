<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Tests\Webhook;

use Symfony\Component\Mailer\Bridge\Mailgun\RemoteEvent\MailgunPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailgun\Webhook\MailgunRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class MailgunRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new MailgunRequestParser(new MailgunPayloadConverter());
    }

    protected function getSecret(): string
    {
        return 'key-0p6mqbf74lb20gzq9f4dhpn9rg3zyk26';
    }
}
