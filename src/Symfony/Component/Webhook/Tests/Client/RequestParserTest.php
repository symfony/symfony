<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Tests\Client;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Webhook\Client\RequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

class RequestParserTest extends TestCase
{
    public function testParseDoesNotMatch()
    {
        $this->expectException(RejectWebhookException::class);

        $request = new Request();
        $parser = new RequestParser();
        $parser->parse($request, '$ecret');
    }
}
