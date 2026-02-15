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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Twig\Environment;

class WebDebugToolbarStreamedResponseListenerTest extends TestCase
{
    #[DataProvider('provideInjectedToolbarHtml')]
    public function testInjectToolbar(string $content, string $expected)
    {
        $listener = new WebDebugToolbarListener($this->getTwigStub());
        $m = new \ReflectionMethod($listener, 'injectToolbar');

        $response = new StreamedResponse($this->createCallbackFromContent($content));

        $m->invoke($listener, $response, Request::create('/'), ['csp_script_nonce' => 'scripto', 'csp_style_nonce' => 'stylo']);
        $this->assertSame($expected, $this->getContentFromStreamedResponse($response));
    }

    public static function provideInjectedToolbarHtml(): array
    {
        return [
            ['<html><head></head><body></body></html>', "<html><head></head><body>\nWDT\n</body></html>"],
            ['<html>
            <head></head>
            <body>
            <textarea><html><head></head><body></body></html></textarea>
            </body>
            </html>', "<html>
            <head></head>
            <body>
            <textarea><html><head></head><body></body></html></textarea>
            \nWDT\n</body>
            </html>"],
        ];
    }

    #[DataProvider('provideRedirects')]
    public function testHtmlRedirectionIsIntercepted(int $statusCode)
    {
        $response = new StreamedResponse($this->createCallbackFromContent('Some content'), $statusCode);
        $response->headers->set('Location', 'https://example.com/');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $event = new ResponseEvent($this->createStub(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigStub('Redirection'), true);
        $listener->onKernelResponse($event);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Redirection', $this->getContentFromStreamedResponse($response));
    }

    public static function provideRedirects(): array
    {
        return [
            [301],
            [302],
        ];
    }

    public function testToolbarIsInjected()
    {
        $response = new StreamedResponse($this->createCallbackFromContent('<html><head></head><body></body></html>'));
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new ResponseEvent($this->createStub(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigStub());
        $listener->onKernelResponse($event);

        $this->assertSame("<html><head></head><body>\nWDT\n</body></html>", $this->getContentFromStreamedResponse($response));
    }

    public function testToolbarIsNotInjectedOnIncompleteHtmlResponses()
    {
        $response = new StreamedResponse($this->createCallbackFromContent('<div>Some content</div>'));
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new ResponseEvent($this->createStub(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigStub());
        $listener->onKernelResponse($event);

        $this->assertSame('<div>Some content</div>', $this->getContentFromStreamedResponse($response));
    }

    private function getTwigStub(string $render = 'WDT'): Environment
    {
        $templating = $this->createStub(Environment::class);
        $templating->method('render')
            ->willReturn($render);

        return $templating;
    }

    private function createCallbackFromContent(string $content): callable
    {
        return static function () use ($content): void {
            echo $content;
        };
    }

    private function getContentFromStreamedResponse(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }
}
