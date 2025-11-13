<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fragment;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\Fragment\EsiFragmentRenderer;
use Symfony\Component\HttpKernel\Fragment\InlineFragmentRenderer;
use Symfony\Component\HttpKernel\HttpCache\Esi;

class EsiFragmentRendererTest extends TestCase
{
    public function testRenderFallbackToInlineStrategyIfEsiNotSupported()
    {
        $strategy = new EsiFragmentRenderer(new Esi(), $this->getInlineStrategy(true));
        $strategy->render('/', Request::create('/'));
    }

    public function testRenderFallbackWithScalar()
    {
        $strategy = new EsiFragmentRenderer(new Esi(), $this->getInlineStrategy(true), new UriSigner('foo'));
        $request = Request::create('/');
        $reference = new ControllerReference('main_controller', ['foo' => [true]], []);
        $strategy->render($reference, $request);
    }

    public function testRender()
    {
        $strategy = new EsiFragmentRenderer(new Esi(), $this->getInlineStrategy());

        $request = Request::create('/');
        $request->setLocale('fr');
        $request->headers->set('Surrogate-Capability', 'ESI/1.0');

        $this->assertEquals('<esi:include src="/" />', $strategy->render('/', $request)->getContent());
        $this->assertEquals("<esi:comment text=\"This is a comment\" />\n<esi:include src=\"/\" />", $strategy->render('/', $request, ['comment' => 'This is a comment'])->getContent());
        $this->assertEquals('<esi:include src="/" alt="foo" />', $strategy->render('/', $request, ['alt' => 'foo'])->getContent());
    }

    public function testRenderControllerReference()
    {
        $signer = new UriSigner('foo');
        $strategy = new EsiFragmentRenderer(new Esi(), $this->getInlineStrategy(), $signer);

        $request = Request::create('/');
        $request->setLocale('fr');
        $request->headers->set('Surrogate-Capability', 'ESI/1.0');

        $reference = new ControllerReference('main_controller', [], []);
        $altReference = new ControllerReference('alt_controller', [], []);

        $this->assertMatchesRegularExpression(
            '#^<esi:include src="/_fragment\?_hash=.+&_path=_format%3Dhtml%26_locale%3Dfr%26_controller%3Dmain_controller" alt="/_fragment\?_hash=.+&_path=_format%3Dhtml%26_locale%3Dfr%26_controller%3Dalt_controller" />$#',
            $strategy->render($reference, $request, ['alt' => $altReference])->getContent()
        );
    }

    public function testRenderControllerReferenceWithAbsoluteUri()
    {
        $signer = new UriSigner('foo');
        $strategy = new EsiFragmentRenderer(new Esi(), $this->getInlineStrategy(), $signer);

        $request = Request::create('http://localhost/');
        $request->setLocale('fr');
        $request->headers->set('Surrogate-Capability', 'ESI/1.0');

        $reference = new ControllerReference('main_controller', [], []);
        $altReference = new ControllerReference('alt_controller', [], []);

        $this->assertMatchesRegularExpression(
            '#^<esi:include src="http://localhost/_fragment\?_hash=.+&_path=_format%3Dhtml%26_locale%3Dfr%26_controller%3Dmain_controller" alt="http://localhost/_fragment\?_hash=.+&_path=_format%3Dhtml%26_locale%3Dfr%26_controller%3Dalt_controller" />$#',
            $strategy->render($reference, $request, ['alt' => $altReference, 'absolute_uri' => true])->getContent()
        );
    }

    public function testRenderControllerReferenceWithoutSignerThrowsException()
    {
        $this->expectException(\LogicException::class);
        $strategy = new EsiFragmentRenderer(new Esi(), $this->getInlineStrategy());

        $request = Request::create('/');
        $request->setLocale('fr');
        $request->headers->set('Surrogate-Capability', 'ESI/1.0');

        $strategy->render(new ControllerReference('main_controller'), $request);
    }

    public function testRenderAltControllerReferenceWithoutSignerThrowsException()
    {
        $this->expectException(\LogicException::class);
        $strategy = new EsiFragmentRenderer(new Esi(), $this->getInlineStrategy());

        $request = Request::create('/');
        $request->setLocale('fr');
        $request->headers->set('Surrogate-Capability', 'ESI/1.0');

        $strategy->render('/', $request, ['alt' => new ControllerReference('alt_controller')]);
    }

    private function getInlineStrategy($called = false)
    {
        $inline = $this->createMock(InlineFragmentRenderer::class);

        if ($called) {
            $inline->expects($this->once())->method('render');
        }

        return $inline;
    }
}
