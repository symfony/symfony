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
use Symfony\Component\HttpFoundation\RequestMatcher\IsJsonRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Client\RequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

class RequestParserTest extends TestCase
{
    public function testParseDoesNotMatch()
    {
        $this->expectException(RejectWebhookException::class);
        (new RequestParser())->parse(new Request(), '$ecret');
    }

    public function testParseRejectsAJsonBodyThatIsNotAnArray()
    {
        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Request body is malformed.');

        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '1');
        $parser = new class extends AbstractRequestParser {
            protected function getRequestMatcher(): RequestMatcherInterface
            {
                return new IsJsonRequestMatcher();
            }

            protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?RemoteEvent
            {
                $request->toArray();

                return null;
            }
        };
        $parser->parse($request, '$ecret');
    }
}
