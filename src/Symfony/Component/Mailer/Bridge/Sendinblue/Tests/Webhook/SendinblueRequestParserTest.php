<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendinblue\Tests\Webhook;

use Symfony\Component\Mailer\Bridge\Sendinblue\RemoteEvent\SendinbluePayloadConverter;
use Symfony\Component\Mailer\Bridge\Sendinblue\Webhook\SendinblueRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class SendinblueRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new SendinblueRequestParser(new SendinbluePayloadConverter());
    }
}
