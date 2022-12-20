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
use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;

class EsiTest extends TestCase
{
    public function testHasSurrogateEsiCapability()
    {
        $esi = new Esi();

        $request = Request::create('/');
        $request->headers->set('Surrogate-Capability', 'abc="ESI/1.0"');
        self::assertTrue($esi->hasSurrogateCapability($request));

        $request = Request::create('/');
        $request->headers->set('Surrogate-Capability', 'foobar');
        self::assertFalse($esi->hasSurrogateCapability($request));

        $request = Request::create('/');
        self::assertFalse($esi->hasSurrogateCapability($request));
    }

    public function testAddSurrogateEsiCapability()
    {
        $esi = new Esi();

        $request = Request::create('/');
        $esi->addSurrogateCapability($request);
        self::assertEquals('symfony="ESI/1.0"', $request->headers->get('Surrogate-Capability'));

        $esi->addSurrogateCapability($request);
        self::assertEquals('symfony="ESI/1.0", symfony="ESI/1.0"', $request->headers->get('Surrogate-Capability'));
    }

    public function testAddSurrogateControl()
    {
        $esi = new Esi();

        $response = new Response('foo <esi:include src="" />');
        $esi->addSurrogateControl($response);
        self::assertEquals('content="ESI/1.0"', $response->headers->get('Surrogate-Control'));

        $response = new Response('foo');
        $esi->addSurrogateControl($response);
        self::assertEquals('', $response->headers->get('Surrogate-Control'));
    }

    public function testNeedsEsiParsing()
    {
        $esi = new Esi();

        $response = new Response();
        $response->headers->set('Surrogate-Control', 'content="ESI/1.0"');
        self::assertTrue($esi->needsParsing($response));

        $response = new Response();
        self::assertFalse($esi->needsParsing($response));
    }

    public function testRenderIncludeTag()
    {
        $esi = new Esi();

        self::assertEquals('<esi:include src="/" onerror="continue" alt="/alt" />', $esi->renderIncludeTag('/', '/alt', true));
        self::assertEquals('<esi:include src="/" alt="/alt" />', $esi->renderIncludeTag('/', '/alt', false));
        self::assertEquals('<esi:include src="/" onerror="continue" />', $esi->renderIncludeTag('/'));
        self::assertEquals('<esi:comment text="some comment" />'."\n".'<esi:include src="/" onerror="continue" alt="/alt" />', $esi->renderIncludeTag('/', '/alt', true, 'some comment'));
    }

    public function testProcessDoesNothingIfContentTypeIsNotHtml()
    {
        $esi = new Esi();

        $request = Request::create('/');
        $response = new Response();
        $response->headers->set('Content-Type', 'text/plain');
        self::assertSame($response, $esi->process($request, $response));

        self::assertFalse($response->headers->has('x-body-eval'));
    }

    public function testMultilineEsiRemoveTagsAreRemoved()
    {
        $esi = new Esi();

        $request = Request::create('/');
        $response = new Response('<esi:remove> <a href="http://www.example.com">www.example.com</a> </esi:remove> Keep this'."<esi:remove>\n <a>www.example.com</a> </esi:remove> And this");
        self::assertSame($response, $esi->process($request, $response));

        self::assertEquals(' Keep this And this', $response->getContent());
    }

    public function testCommentTagsAreRemoved()
    {
        $esi = new Esi();

        $request = Request::create('/');
        $response = new Response('<esi:comment text="some comment &gt;" /> Keep this');
        self::assertSame($response, $esi->process($request, $response));

        self::assertEquals(' Keep this', $response->getContent());
    }

    public function testProcess()
    {
        $esi = new Esi();

        $request = Request::create('/');
        $response = new Response('foo <esi:comment text="some comment" /><esi:include src="..." alt="alt" onerror="continue" />');
        self::assertSame($response, $esi->process($request, $response));

        self::assertEquals('foo <?php echo $this->surrogate->handle($this, \'...\', \'alt\', true) ?>'."\n", $response->getContent());
        self::assertEquals('ESI', $response->headers->get('x-body-eval'));

        $response = new Response('foo <esi:comment text="some comment" /><esi:include src="foo\'" alt="bar\'" onerror="continue" />');
        self::assertSame($response, $esi->process($request, $response));

        self::assertEquals('foo <?php echo $this->surrogate->handle($this, \'foo\\\'\', \'bar\\\'\', true) ?>'."\n", $response->getContent());

        $response = new Response('foo <esi:include src="..." />');
        self::assertSame($response, $esi->process($request, $response));

        self::assertEquals('foo <?php echo $this->surrogate->handle($this, \'...\', \'\', false) ?>'."\n", $response->getContent());

        $response = new Response('foo <esi:include src="..."></esi:include>');
        self::assertSame($response, $esi->process($request, $response));

        self::assertEquals('foo <?php echo $this->surrogate->handle($this, \'...\', \'\', false) ?>'."\n", $response->getContent());
    }

    public function testProcessEscapesPhpTags()
    {
        $esi = new Esi();

        $request = Request::create('/');
        $response = new Response('<?php <? <% <script language=php>');
        self::assertSame($response, $esi->process($request, $response));

        self::assertEquals('<?php echo "<?"; ?>php <?php echo "<?"; ?> <?php echo "<%"; ?> <?php echo "<s"; ?>cript language=php>', $response->getContent());
    }

    public function testProcessWhenNoSrcInAnEsi()
    {
        self::expectException(\RuntimeException::class);
        $esi = new Esi();

        $request = Request::create('/');
        $response = new Response('foo <esi:include />');
        self::assertSame($response, $esi->process($request, $response));
    }

    public function testProcessRemoveSurrogateControlHeader()
    {
        $esi = new Esi();

        $request = Request::create('/');
        $response = new Response('foo <esi:include src="..." />');
        $response->headers->set('Surrogate-Control', 'content="ESI/1.0"');
        self::assertSame($response, $esi->process($request, $response));
        self::assertEquals('ESI', $response->headers->get('x-body-eval'));

        $response->headers->set('Surrogate-Control', 'no-store, content="ESI/1.0"');
        self::assertSame($response, $esi->process($request, $response));
        self::assertEquals('ESI', $response->headers->get('x-body-eval'));
        self::assertEquals('no-store', $response->headers->get('surrogate-control'));

        $response->headers->set('Surrogate-Control', 'content="ESI/1.0", no-store');
        self::assertSame($response, $esi->process($request, $response));
        self::assertEquals('ESI', $response->headers->get('x-body-eval'));
        self::assertEquals('no-store', $response->headers->get('surrogate-control'));
    }

    public function testHandle()
    {
        $esi = new Esi();
        $cache = $this->getCache(Request::create('/'), new Response('foo'));
        self::assertEquals('foo', $esi->handle($cache, '/', '/alt', true));
    }

    public function testHandleWhenResponseIsNot200()
    {
        self::expectException(\RuntimeException::class);
        $esi = new Esi();
        $response = new Response('foo');
        $response->setStatusCode(404);
        $cache = $this->getCache(Request::create('/'), $response);
        $esi->handle($cache, '/', '/alt', false);
    }

    public function testHandleWhenResponseIsNot200AndErrorsAreIgnored()
    {
        $esi = new Esi();
        $response = new Response('foo');
        $response->setStatusCode(404);
        $cache = $this->getCache(Request::create('/'), $response);
        self::assertEquals('', $esi->handle($cache, '/', '/alt', true));
    }

    public function testHandleWhenResponseIsNot200AndAltIsPresent()
    {
        $esi = new Esi();
        $response1 = new Response('foo');
        $response1->setStatusCode(404);
        $response2 = new Response('bar');
        $cache = $this->getCache(Request::create('/'), [$response1, $response2]);
        self::assertEquals('bar', $esi->handle($cache, '/', '/alt', false));
    }

    public function testHandleWhenResponseIsNotModified()
    {
        $esi = new Esi();
        $response = new Response('');
        $response->setStatusCode(304);
        $cache = $this->getCache(Request::create('/'), $response);
        self::assertEquals('', $esi->handle($cache, '/', '/alt', true));
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
