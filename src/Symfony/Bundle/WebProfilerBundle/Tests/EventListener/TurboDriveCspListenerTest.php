<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PsrHttpMessage\Tests\Fixtures\App\Kernel;
use Symfony\Bundle\WebProfilerBundle\EventListener\TurboDriveCspListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 *  @author David Petrásek <davidpetrasek@hotmail.cz>
 */
class TurboDriveCspListenerTest extends TestCase
{
    public function testInjectsScriptAndUpdatesCsp()
    {
        // Create a Request: HTML format, no Turbo header, and a non-wdt route.
        $request = new Request();
        $request->attributes->set('_route', 'some_route');
        $request->setRequestFormat('html');

        // Create a Response with a simple HTML page that includes </head>
        $originalContent = '<html><head><title>Test</title></head><body>Content</body></html>';
        $response = new Response($originalContent);
        // Set an initial CSP header (for testing the hash update)
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'");

        // Create the ResponseEvent
        $responseEvent = new ResponseEvent(new DummyKernel(), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        // Instantiate and invoke your subscriber
        $subscriber = new TurboDriveCspListener(new Kernel('dev', true));
        $subscriber->onKernelResponse($responseEvent);

        // Get the modified content and CSP header
        $modifiedContent = $response->getContent();
        $modifiedCsp = $response->headers->get('Content-Security-Policy');

        // Assert that the script tag was injected before </head>
        $this->assertStringContainsString('<script>', $modifiedContent);
        $this->assertStringContainsString('</head>', $modifiedContent);
        $this->assertMatchesRegularExpression('/<script>.*?<\/script><\/head>/s', $modifiedContent);

        // Compute the expected SHA-256 hash for the inline script content
        $scriptContent = <<<'EOD'
        document.addEventListener('turbo:before-fetch-request', (event) =>
        {
            var wdt = document.querySelector('.sf-toolbar');
            if (wdt)
            {
                let wdtStyle = wdt.nextElementSibling;
                let wdtScript = wdtStyle.nextElementSibling;

                if (wdtStyle.nonce) {event.detail.fetchOptions.headers['X-SymfonyProfiler-Style-Nonce'] = wdtStyle.nonce;}
                if (wdtScript.nonce) {event.detail.fetchOptions.headers['X-SymfonyProfiler-Script-Nonce'] = wdtScript.nonce;}
            }
        });
    EOD;

        $expectedHash = "'sha256-".base64_encode(hash('sha256', $scriptContent, true))."'";
        // Assert that the updated CSP header contains the expected hash
        $this->assertStringContainsString($expectedHash, $modifiedCsp);
    }

    public function testSkipsTurboDriveRequest()
    {
        // Create a Request that includes the Turbo header
        $request = new Request();
        $request->attributes->set('_route', 'some_route');
        $request->setRequestFormat('html');
        $request->headers->set('X-Turbo-Request-Id', '123');

        $originalContent = '<html><head><title>Test</title></head><body>Content</body></html>';
        $response = new Response($originalContent);
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'");

        $event = new ResponseEvent(new DummyKernel(), $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber = new TurboDriveCspListener(new Kernel('dev', true));

        $subscriber->onKernelResponse($event);

        // Since the Turbo header is present, the content should remain unchanged.
        $this->assertEquals($originalContent, $response->getContent());
    }
}

class DummyKernel implements HttpKernelInterface
{
    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        return new Response();
    }
}
