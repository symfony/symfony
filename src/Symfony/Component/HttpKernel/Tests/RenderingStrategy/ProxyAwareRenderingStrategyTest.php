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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\RenderingStrategy\ProxyAwareRenderingStrategy;

class ProxyAwareRenderingStrategyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getGenerateProxyUriData
     */
    public function testGenerateProxyUri($uri, $controller)
    {
        $this->assertEquals($uri, $this->getStrategy()->doGenerateProxyUri($controller, Request::create('/')));
    }

    public function getGenerateProxyUriData()
    {
        return array(
            array('http://localhost/_proxy?_path=_format%3Dhtml%26_controller%3Dcontroller', new ControllerReference('controller', array(), array())),
            array('http://localhost/_proxy?_path=_format%3Dxml%26_controller%3Dcontroller', new ControllerReference('controller', array('_format' => 'xml'), array())),
            array('http://localhost/_proxy?_path=foo%3Dfoo%26_format%3Djson%26_controller%3Dcontroller', new ControllerReference('controller', array('foo' => 'foo', '_format' => 'json'), array())),
            array('http://localhost/_proxy?bar=bar&_path=foo%3Dfoo%26_format%3Dhtml%26_controller%3Dcontroller', new ControllerReference('controller', array('foo' => 'foo'), array('bar' => 'bar'))),
            array('http://localhost/_proxy?foo=foo&_path=_format%3Dhtml%26_controller%3Dcontroller', new ControllerReference('controller', array(), array('foo' => 'foo'))),
        );
    }

    public function testGenerateProxyUriWithARequest()
    {
        $request = Request::create('/');
        $request->attributes->set('_format', 'json');
        $controller = new ControllerReference('controller', array(), array());

        $this->assertEquals('http://localhost/_proxy?_path=_format%3Djson%26_controller%3Dcontroller', $this->getStrategy()->doGenerateProxyUri($controller, $request));
    }

    private function getStrategy()
    {
        return new Strategy();
    }
}

class Strategy extends ProxyAwareRenderingStrategy
{
    public function render($uri, Request $request, array $options = array()) {}
    public function getName() {}

    public function doGenerateProxyUri(ControllerReference $reference, Request $request)
    {
        return parent::generateProxyUri($reference, $request);
    }
}
