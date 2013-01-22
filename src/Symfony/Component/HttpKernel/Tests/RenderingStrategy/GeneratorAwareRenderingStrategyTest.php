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
use Symfony\Component\HttpKernel\RenderingStrategy\GeneratorAwareRenderingStrategy;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class GeneratorAwareRenderingStrategyTest extends AbstractRenderingStrategyTest
{
    protected function setUp()
    {
        if (!interface_exists('Symfony\Component\Routing\Generator\UrlGeneratorInterface')) {
            $this->markTestSkipped('The "Routing" component is not available');
        }
    }

    /**
     * @expectedException \LogicException
     */
    public function testGenerateProxyUriWithNoGenerator()
    {
        $strategy = new Strategy();
        $strategy->doGenerateProxyUri(new ControllerReference('controller', array(), array()), Request::create('/'));
    }

    /**
     * @expectedException \LogicException
     */
    public function testGenerateProxyUriWhenRouteNotFound()
    {
        $generator = $this->getMock('Symfony\Component\Routing\Generator\UrlGeneratorInterface');
        $generator
            ->expects($this->once())
            ->method('generate')
            ->will($this->throwException(new RouteNotFoundException()))
        ;

        $strategy = new Strategy();
        $strategy->setUrlGenerator($generator);
        $strategy->doGenerateProxyUri(new ControllerReference('controller', array(), array()), Request::create('/'));
    }

    /**
     * @dataProvider getGeneratorProxyUriData
     */
    public function testGenerateProxyUri($uri, $controller)
    {
        $this->assertEquals($uri, $this->getStrategy()->doGenerateProxyUri($controller, Request::create('/')));
    }

    public function getGeneratorProxyUriData()
    {
        return array(
            array('/controller.html', new ControllerReference('controller', array(), array())),
            array('/controller.xml', new ControllerReference('controller', array('_format' => 'xml'), array())),
            array('/controller.json?path=foo%3Dfoo', new ControllerReference('controller', array('foo' => 'foo', '_format' => 'json'), array())),
            array('/controller.html?bar=bar&path=foo%3Dfoo', new ControllerReference('controller', array('foo' => 'foo'), array('bar' => 'bar'))),
            array('/controller.html?foo=foo', new ControllerReference('controller', array(), array('foo' => 'foo'))),
        );
    }

    public function testGenerateProxyUriWithARequest()
    {
        $request = Request::create('/');
        $request->attributes->set('_format', 'json');
        $controller = new ControllerReference('controller', array(), array());

        $this->assertEquals('/controller.json', $this->getStrategy()->doGenerateProxyUri($controller, $request));
    }

    private function getStrategy()
    {
        $strategy = new Strategy();
        $strategy->setUrlGenerator($this->getUrlGenerator());

        return $strategy;
    }
}

class Strategy extends GeneratorAwareRenderingStrategy
{
    public function render($uri, Request $request, array $options = array()) {}
    public function getName() {}

    public function doGenerateProxyUri(ControllerReference $reference, Request $request)
    {
        return parent::generateProxyUri($reference, $request);
    }
}
