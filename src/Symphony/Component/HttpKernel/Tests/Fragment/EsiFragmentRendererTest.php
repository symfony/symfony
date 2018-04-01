<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\Fragment;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpKernel\Controller\ControllerReference;
use Symphony\Component\HttpKernel\Fragment\EsiFragmentRenderer;
use Symphony\Component\HttpKernel\HttpCache\Esi;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\UriSigner;

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
        $reference = new ControllerReference('main_controller', array('foo' => array(true)), array());
        $strategy->render($reference, $request);
    }

    public function testRender()
    {
        $strategy = new EsiFragmentRenderer(new Esi(), $this->getInlineStrategy());

        $request = Request::create('/');
        $request->setLocale('fr');
        $request->headers->set('Surrogate-Capability', 'ESI/1.0');

        $this->assertEquals('<esi:include src="/" />', $strategy->render('/', $request)->getContent());
        $this->assertEquals("<esi:comment text=\"This is a comment\" />\n<esi:include src=\"/\" />", $strategy->render('/', $request, array('comment' => 'This is a comment'))->getContent());
        $this->assertEquals('<esi:include src="/" alt="foo" />', $strategy->render('/', $request, array('alt' => 'foo'))->getContent());
    }

    public function testRenderControllerReference()
    {
        $signer = new UriSigner('foo');
        $strategy = new EsiFragmentRenderer(new Esi(), $this->getInlineStrategy(), $signer);

        $request = Request::create('/');
        $request->setLocale('fr');
        $request->headers->set('Surrogate-Capability', 'ESI/1.0');

        $reference = new ControllerReference('main_controller', array(), array());
        $altReference = new ControllerReference('alt_controller', array(), array());

        $this->assertEquals(
            '<esi:include src="/_fragment?_path=_format%3Dhtml%26_locale%3Dfr%26_controller%3Dmain_controller&_hash=Jz1P8NErmhKTeI6onI1EdAXTB85359MY3RIk5mSJ60w%3D" alt="/_fragment?_path=_format%3Dhtml%26_locale%3Dfr%26_controller%3Dalt_controller&_hash=iPJEdRoUpGrM1ztqByiorpfMPtiW%2FOWwdH1DBUXHhEc%3D" />',
            $strategy->render($reference, $request, array('alt' => $altReference))->getContent()
        );
    }

    /**
     * @expectedException \LogicException
     */
    public function testRenderControllerReferenceWithoutSignerThrowsException()
    {
        $strategy = new EsiFragmentRenderer(new Esi(), $this->getInlineStrategy());

        $request = Request::create('/');
        $request->setLocale('fr');
        $request->headers->set('Surrogate-Capability', 'ESI/1.0');

        $strategy->render(new ControllerReference('main_controller'), $request);
    }

    /**
     * @expectedException \LogicException
     */
    public function testRenderAltControllerReferenceWithoutSignerThrowsException()
    {
        $strategy = new EsiFragmentRenderer(new Esi(), $this->getInlineStrategy());

        $request = Request::create('/');
        $request->setLocale('fr');
        $request->headers->set('Surrogate-Capability', 'ESI/1.0');

        $strategy->render('/', $request, array('alt' => new ControllerReference('alt_controller')));
    }

    private function getInlineStrategy($called = false)
    {
        $inline = $this->getMockBuilder('Symphony\Component\HttpKernel\Fragment\InlineFragmentRenderer')->disableOriginalConstructor()->getMock();

        if ($called) {
            $inline->expects($this->once())->method('render');
        }

        return $inline;
    }
}
