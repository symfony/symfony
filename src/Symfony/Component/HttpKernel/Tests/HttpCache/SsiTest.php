<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\HttpCache;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;
use Symfony\Component\HttpKernel\HttpCache\Ssi;

class SsiTest extends TestCase
{
    public function testHasSurrogateSsiCapability()
    {
        $ssi = new Ssi();

        $request = Request::create('/');
        $request->headers->set('Surrogate-Capability', 'abc="SSI/1.0"');
        self::assertTrue($ssi->hasSurrogateCapability($request));

        $request = Request::create('/');
        $request->headers->set('Surrogate-Capability', 'foobar');
        self::assertFalse($ssi->hasSurrogateCapability($request));

        $request = Request::create('/');
        self::assertFalse($ssi->hasSurrogateCapability($request));
    }

    public function testAddSurrogateSsiCapability()
    {
        $ssi = new Ssi();

        $request = Request::create('/');
        $ssi->addSurrogateCapability($request);
        self::assertEquals('symfony="SSI/1.0"', $request->headers->get('Surrogate-Capability'));

        $ssi->addSurrogateCapability($request);
        self::assertEquals('symfony="SSI/1.0", symfony="SSI/1.0"', $request->headers->get('Surrogate-Capability'));
    }

    public function testAddSurrogateControl()
    {
        $ssi = new Ssi();

        $response = new Response('foo <!--#include virtual="" -->');
        $ssi->addSurrogateControl($response);
        self::assertEquals('content="SSI/1.0"', $response->headers->get('Surrogate-Control'));

        $response = new Response('foo');
        $ssi->addSurrogateControl($response);
        self::assertEquals('', $response->headers->get('Surrogate-Control'));
    }

    public function testNeedsSsiParsing()
    {
        $ssi = new Ssi();

        $response = new Response();
        $response->headers->set('Surrogate-Control', 'content="SSI/1.0"');
        self::assertTrue($ssi->needsParsing($response));

        $response = new Response();
        self::assertFalse($ssi->needsParsing($response));
    }

    public function testRenderIncludeTag()
    {
        $ssi = new Ssi();

        self::assertEquals('<!--#include virtual="/" -->', $ssi->renderIncludeTag('/', '/alt', true));
        self::assertEquals('<!--#include virtual="/" -->', $ssi->renderIncludeTag('/', '/alt', false));
        self::assertEquals('<!--#include virtual="/" -->', $ssi->renderIncludeTag('/'));
    }

    public function testProcessDoesNothingIfContentTypeIsNotHtml()
    {
        $ssi = new Ssi();

        $request = Request::create('/');
        $response = new Response();
        $response->headers->set('Content-Type', 'text/plain');
        $ssi->process($request, $response);

        self::assertFalse($response->headers->has('x-body-eval'));
    }

    public function testProcess()
    {
        $ssi = new Ssi();

        $request = Request::create('/');
        $response = new Response('foo <!--#include virtual="..." -->');
        $ssi->process($request, $response);

        self::assertEquals('foo <?php echo $this->surrogate->handle($this, \'...\', \'\', false) ?>'."\n", $response->getContent());
        self::assertEquals('SSI', $response->headers->get('x-body-eval'));

        $response = new Response('foo <!--#include virtual="foo\'" -->');
        $ssi->process($request, $response);

        self::assertEquals("foo <?php echo \$this->surrogate->handle(\$this, 'foo\\'', '', false) ?>"."\n", $response->getContent());
    }

    public function testProcessEscapesPhpTags()
    {
        $ssi = new Ssi();

        $request = Request::create('/');
        $response = new Response('<?php <? <% <script language=php>');
        $ssi->process($request, $response);

        self::assertEquals('<?php echo "<?"; ?>php <?php echo "<?"; ?> <?php echo "<%"; ?> <?php echo "<s"; ?>cript language=php>', $response->getContent());
    }

    public function testProcessWhenNoSrcInAnSsi()
    {
        self::expectException(\RuntimeException::class);
        $ssi = new Ssi();

        $request = Request::create('/');
        $response = new Response('foo <!--#include -->');
        $ssi->process($request, $response);
    }

    public function testProcessRemoveSurrogateControlHeader()
    {
        $ssi = new Ssi();

        $request = Request::create('/');
        $response = new Response('foo <!--#include virtual="..." -->');
        $response->headers->set('Surrogate-Control', 'content="SSI/1.0"');
        $ssi->process($request, $response);
        self::assertEquals('SSI', $response->headers->get('x-body-eval'));

        $response->headers->set('Surrogate-Control', 'no-store, content="SSI/1.0"');
        $ssi->process($request, $response);
        self::assertEquals('SSI', $response->headers->get('x-body-eval'));
        self::assertEquals('no-store', $response->headers->get('surrogate-control'));

        $response->headers->set('Surrogate-Control', 'content="SSI/1.0", no-store');
        $ssi->process($request, $response);
        self::assertEquals('SSI', $response->headers->get('x-body-eval'));
        self::assertEquals('no-store', $response->headers->get('surrogate-control'));
    }

    public function testHandle()
    {
        $ssi = new Ssi();
        $cache = $this->getCache(Request::create('/'), new Response('foo'));
        self::assertEquals('foo', $ssi->handle($cache, '/', '/alt', true));
    }

    public function testHandleWhenResponseIsNot200()
    {
        self::expectException(\RuntimeException::class);
        $ssi = new Ssi();
        $response = new Response('foo');
        $response->setStatusCode(404);
        $cache = $this->getCache(Request::create('/'), $response);
        $ssi->handle($cache, '/', '/alt', false);
    }

    public function testHandleWhenResponseIsNot200AndErrorsAreIgnored()
    {
        $ssi = new Ssi();
        $response = new Response('foo');
        $response->setStatusCode(404);
        $cache = $this->getCache(Request::create('/'), $response);
        self::assertEquals('', $ssi->handle($cache, '/', '/alt', true));
    }

    public function testHandleWhenResponseIsNot200AndAltIsPresent()
    {
        $ssi = new Ssi();
        $response1 = new Response('foo');
        $response1->setStatusCode(404);
        $response2 = new Response('bar');
        $cache = $this->getCache(Request::create('/'), [$response1, $response2]);
        self::assertEquals('bar', $ssi->handle($cache, '/', '/alt', false));
    }

    protected function getCache($request, $response)
    {
        $cache = self::getMockBuilder(HttpCache::class)->setMethods(['getRequest', 'handle'])->disableOriginalConstructor()->getMock();
        $cache->expects(self::any())
              ->method('getRequest')
              ->willReturn($request)
        ;
        if (\is_array($response)) {
            $cache->expects(self::any())
                  ->method('handle')
                  ->will(self::onConsecutiveCalls(...$response))
            ;
        } else {
            $cache->expects(self::any())
                  ->method('handle')
                  ->willReturn($response)
            ;
        }

        return $cache;
    }
}
