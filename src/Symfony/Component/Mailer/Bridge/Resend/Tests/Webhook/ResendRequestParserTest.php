<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Resend\Tests\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Resend\RemoteEvent\ResendPayloadConverter;
use Symfony\Component\Mailer\Bridge\Resend\Webhook\ResendRequestParser;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Component\Webhook\Test\AbstractRequestParserTestCase;

class ResendRequestParserTest extends AbstractRequestParserTestCase
{
    protected function createRequestParser(): RequestParserInterface
    {
        return new ResendRequestParser(new ResendPayloadConverter());
    }

    protected function getSecret(): string
    {
        return 'whsec_ESwTAuuIe3yfH4DgdgI+ENsiNzPAGdp+';
    }

    protected function createRequest(string $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], [
            'Content-Type' => 'application/json',
            'HTTP_svix-id' => '172c41ce-ba6d-4281-8a7a-541faa725748',
            'HTTP_svix-timestamp' => '1712569389',
            'HTTP_svix-signature' => 'v1,4wjuRp64yC/2itgCQwl2xPePVwSPTdPbXLIY6IxGLTA=',
        ], str_replace("\n", "\r\n", $payload));
    }
}
