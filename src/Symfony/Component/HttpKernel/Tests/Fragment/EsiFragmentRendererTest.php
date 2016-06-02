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

use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\Fragment\EsiFragmentRenderer;
use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\UriSigner;

class EsiFragmentRendererTest extends \PHPUnit_Framework_TestCase
{
    public function testRenderFallbackToInlineStrategyIfEsiNotSupported()
    {
        $strategy = new EsiFragmentRenderer(new Esi(), $this->getInlineStrategy(true));
        $strategy->render('/', Request::create('/'));
    }

    /**
     * @group legacy
     */
    public function testRenderFallbackWithObjectAttributesIsDeprecated()
    {
        $deprecations = array();
        set_error_handler(function ($type, $message) use (&$deprecations) {
            if (E_USER_DEPRECATED !== $type) {
                restore_error_handler();

                return call_user_func_array('PHPUnit_Util_ErrorHandler::handleError', func_get_args());
            }

            $deprecations[] = $message;
        });

        $strategy = new EsiFragmentRenderer(new Esi(), $this->getInlineStrategy(true), new UriSigner('foo'));

        $request = Request::create('/');

        $reference = new ControllerReference('main_controller', array('foo' => array('a' => array(), 'b' => new \stdClass())), array());

        $strategy->render($reference, $request);

        restore_error_handler();

        $this->assertCount(1, $deprecations);
        $this->assertContains('Passing objects as part of URI attributes to the ESI and SSI rendering strategies is deprecated', $deprecations[0]);
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
        $inline = $this->getMockBuilder('Symfony\Component\HttpKernel\Fragment\InlineFragmentRenderer')->disableOriginalConstructor()->getMock();

        if ($called) {
            $inline->expects($this->once())->method('render');
        }

        return $inline;
    }
}
