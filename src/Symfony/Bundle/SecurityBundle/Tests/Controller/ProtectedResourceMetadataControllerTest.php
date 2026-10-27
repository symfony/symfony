<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Controller\ProtectedResourceMetadataController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProtectedResourceMetadataControllerTest extends TestCase
{
    public function testServesTheMetadataOfTheFirewall()
    {
        $controller = new ProtectedResourceMetadataController([
            'main' => [
                'resource' => 'https://api.example.com',
                'authorization_servers' => ['https://accounts.example.com'],
                'bearer_methods_supported' => ['header'],
            ],
            'admin' => [
                'resource' => 'https://api.example.com/admin',
            ],
        ]);

        $response = $controller(Request::create('https://api.example.com/.well-known/oauth-protected-resource'), 'main');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame([
            'resource' => 'https://api.example.com',
            'authorization_servers' => ['https://accounts.example.com'],
            'bearer_methods_supported' => ['header'],
        ], json_decode($response->getContent(), true));
    }

    public function testServesPlainSlashes()
    {
        $controller = new ProtectedResourceMetadataController([
            'main' => ['resource' => 'https://api.example.com'],
        ]);

        $response = $controller(Request::create('https://api.example.com/.well-known/oauth-protected-resource'), 'main');

        $this->assertSame('{"resource":"https://api.example.com"}', $response->getContent());
    }

    public function testAnyOriginCanReadTheDocument()
    {
        $controller = new ProtectedResourceMetadataController([
            'main' => ['resource' => 'https://api.example.com'],
        ]);

        $response = $controller(Request::create('https://api.example.com/.well-known/oauth-protected-resource', 'GET', [], [], [], ['HTTP_ORIGIN' => 'https://client.example.org']), 'main');

        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testAnswersThePreflightOfAnyOrigin()
    {
        $controller = new ProtectedResourceMetadataController([
            'main' => ['resource' => 'https://api.example.com'],
        ]);

        $response = $controller(Request::create('https://api.example.com/.well-known/oauth-protected-resource', 'OPTIONS', [], [], [], [
            'HTTP_ORIGIN' => 'https://client.example.org',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'mcp-protocol-version',
        ]), 'main');

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('GET', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function testServesThePinnedResourceOfAFirewallCoveringAPath()
    {
        $controller = new ProtectedResourceMetadataController([
            'admin' => ['resource' => 'https://api.example.com/admin'],
        ]);

        $response = $controller(Request::create('https://api.example.com/.well-known/oauth-protected-resource/admin'), 'admin');

        $this->assertSame(['resource' => 'https://api.example.com/admin'], json_decode($response->getContent(), true));
    }

    public function testTheResourceDefaultsToTheOriginTheDocumentIsServedFrom()
    {
        $controller = new ProtectedResourceMetadataController([
            'main' => ['authorization_servers' => ['https://accounts.example.com']],
        ]);

        $response = $controller(Request::create('https://api.example.com:8443/.well-known/oauth-protected-resource'), 'main');

        $this->assertSame([
            'resource' => 'https://api.example.com:8443',
            'authorization_servers' => ['https://accounts.example.com'],
        ], json_decode($response->getContent(), true));
    }

    public function testThrowsNotFoundForAnUnknownFirewall()
    {
        $controller = new ProtectedResourceMetadataController([]);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('No protected resource metadata is configured for the "main" firewall.');

        $controller(Request::create('/.well-known/oauth-protected-resource'), 'main');
    }
}
