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
use Symphony\Component\HttpKernel\Fragment\SsiFragmentRenderer;
use Symphony\Component\HttpKernel\HttpCache\Ssi;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\UriSigner;

class SsiFragmentRendererTest extends TestCase
{
    public function testRenderFallbackToInlineStrategyIfSsiNotSupported()
    {
        $strategy = new SsiFragmentRenderer(new Ssi(), $this->getInlineStrategy(true));
        $strategy->render('/', Request::create('/'));
    }

    public function testRender()
    {
        $strategy = new SsiFragmentRenderer(new Ssi(), $this->getInlineStrategy());

        $request = Request::create('/');
        $request->setLocale('fr');
        $request->headers->set('Surrogate-Capability', 'SSI/1.0');

        $this->assertEquals('<!--#include virtual="/" -->', $strategy->render('/', $request)->getContent());
        $this->assertEquals('<!--#include virtual="/" -->', $strategy->render('/', $request, array('comment' => 'This is a comment'))->getContent(), 'Strategy options should not impact the ssi include tag');
    }

    public function testRenderControllerReference()
    {
        $signer = new UriSigner('foo');
        $strategy = new SsiFragmentRenderer(new Ssi(), $this->getInlineStrategy(), $signer);

        $request = Request::create('/');
        $request->setLocale('fr');
        $request->headers->set('Surrogate-Capability', 'SSI/1.0');

        $reference = new ControllerReference('main_controller', array(), array());
        $altReference = new ControllerReference('alt_controller', array(), array());

        $this->assertEquals(
            '<!--#include virtual="/_fragment?_path=_format%3Dhtml%26_locale%3Dfr%26_controller%3Dmain_controller&_hash=Jz1P8NErmhKTeI6onI1EdAXTB85359MY3RIk5mSJ60w%3D" -->',
            $strategy->render($reference, $request, array('alt' => $altReference))->getContent()
        );
    }

    /**
     * @expectedException \LogicException
     */
    public function testRenderControllerReferenceWithoutSignerThrowsException()
    {
        $strategy = new SsiFragmentRenderer(new Ssi(), $this->getInlineStrategy());

        $request = Request::create('/');
        $request->setLocale('fr');
        $request->headers->set('Surrogate-Capability', 'SSI/1.0');

        $strategy->render(new ControllerReference('main_controller'), $request);
    }

    /**
     * @expectedException \LogicException
     */
    public function testRenderAltControllerReferenceWithoutSignerThrowsException()
    {
        $strategy = new SsiFragmentRenderer(new Ssi(), $this->getInlineStrategy());

        $request = Request::create('/');
        $request->setLocale('fr');
        $request->headers->set('Surrogate-Capability', 'SSI/1.0');

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
