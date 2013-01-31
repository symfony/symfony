<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\RenderingStrategy;

use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\RenderingStrategy\EsiRenderingStrategy;
use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpFoundation\Request;

class EsiRenderingStrategyTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }
    }

    public function testRenderFallbackToDefaultStrategyIfNoRequest()
    {
        $strategy = new EsiRenderingStrategy(new Esi(), $this->getDefaultStrategy(true));
        $strategy->render('/', Request::create('/'));
    }

    public function testRenderFallbackToDefaultStrategyIfEsiNotSupported()
    {
        $strategy = new EsiRenderingStrategy(new Esi(), $this->getDefaultStrategy(true));
        $strategy->render('/', Request::create('/'));
    }

    public function testRender()
    {
        $strategy = new EsiRenderingStrategy(new Esi(), $this->getDefaultStrategy());

        $request = Request::create('/');
        $request->headers->set('Surrogate-Capability', 'ESI/1.0');

        $this->assertEquals('<esi:include src="/" />', $strategy->render('/', $request)->getContent());
        $this->assertEquals("<esi:comment text=\"This is a comment\" />\n<esi:include src=\"/\" />", $strategy->render('/', $request, array('comment' => 'This is a comment'))->getContent());
        $this->assertEquals('<esi:include src="/" alt="foo" />', $strategy->render('/', $request, array('alt' => 'foo'))->getContent());
        $this->assertEquals('<esi:include src="http://localhost/_proxy?_path=_format%3Dhtml%26_controller%3Dmain_controller" alt="http://localhost/_proxy?_path=_format%3Dhtml%26_controller%3Dalt_controller" />', $strategy->render(new ControllerReference('main_controller', array(), array()), $request, array('alt' => new ControllerReference('alt_controller', array(), array())))->getContent());
    }

    private function getDefaultStrategy($called = false)
    {
        $default = $this->getMockBuilder('Symfony\Component\HttpKernel\RenderingStrategy\DefaultRenderingStrategy')->disableOriginalConstructor()->getMock();

        if ($called) {
            $default->expects($this->once())->method('render');
        }

        return $default;
    }
}
