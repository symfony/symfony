<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailjet\Tests\Webhook;

use Symfony\Component\Mailer\Bridge\Mailjet\RemoteEvent\MailjetPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailjet\Webhook\MailjetRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class MailjetRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new MailjetRequestParser(new MailjetPayloadConverter());
    }
}
