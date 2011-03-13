<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Tests\Controller;

use Symfony\Bundle\AsseticBundle\Controller\AsseticController;

class AsseticControllerTest extends \PHPUnit_Framework_TestCase
{
    protected $request;
    protected $headers;
    protected $am;
    protected $cache;

    protected $controller;

    protected function setUp()
    {
        if (!class_exists('Assetic\\AssetManager')) {
            $this->markTestSkipped('Assetic is not available.');
        }

        $this->request = $this->getMock('Symfony\\Component\\HttpFoundation\\Request');
        $this->headers = $this->getMock('Symfony\\Component\\HttpFoundation\\ParameterBag');
        $this->request->headers = $this->headers;
        $this->am = $this->getMockBuilder('Assetic\\Factory\\LazyAssetManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->cache = $this->getMock('Assetic\\Cache\\CacheInterface');

        $this->controller = new AsseticController($this->request, $this->am, $this->cache);
    }

    public function testRenderNotFound()
    {
        $this->setExpectedException('Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException');

        $name = 'foo';

        $this->am->expects($this->once())
            ->method('has')
            ->with($name)
            ->will($this->returnValue(false));

        $this->controller->render($name);
    }

    public function testRenderLastModifiedFresh()
    {
        $asset = $this->getMock('Assetic\\Asset\\AssetInterface');

        $name = 'foo';
        $lastModified = strtotime('2010-10-10 10:10:10');
        $ifModifiedSince = gmdate('D, d M Y H:i:s', $lastModified).' GMT';

        $asset->expects($this->any())->method('getFilters')->will($this->returnValue(array()));
        $this->am->expects($this->once())->method('has')->with($name)->will($this->returnValue(true));
        $this->am->expects($this->once())->method('get')->with($name)->will($this->returnValue($asset));
        $asset->expects($this->once())->method('getLastModified')->will($this->returnValue($lastModified));
        $this->headers->expects($this->once())->method('get')->with('If-Modified-Since')->will($this->returnValue($ifModifiedSince));

        $asset->expects($this->never())
            ->method('dump');

        $response = $this->controller->render($name);
        $this->assertEquals(304, $response->getStatusCode(), '->render() sends a Not Modified response when If-Modified-Since is fresh');
    }

    public function testRenderLastModifiedStale()
    {
        $asset = $this->getMock('Assetic\\Asset\\AssetInterface');

        $name = 'foo';
        $content = '==ASSET_CONTENT==';
        $lastModified = strtotime('2010-10-10 10:10:10');
        $ifModifiedSince = gmdate('D, d M Y H:i:s', $lastModified - 300).' GMT';

        $asset->expects($this->any())->method('getFilters')->will($this->returnValue(array()));
        $this->am->expects($this->once())->method('has')->with($name)->will($this->returnValue(true));
        $this->am->expects($this->once())->method('get')->with($name)->will($this->returnValue($asset));
        $asset->expects($this->exactly(2))->method('getLastModified')->will($this->returnValue($lastModified));
        $this->headers->expects($this->once())->method('get')->with('If-Modified-Since')->will($this->returnValue($ifModifiedSince));

        $this->cache->expects($this->once())
            ->method('has')
            ->with($this->isType('string'))
            ->will($this->returnValue(false));
        $asset->expects($this->once())
            ->method('dump')
            ->will($this->returnValue($content));

        $response = $this->controller->render($name);
        $this->assertEquals(200, $response->getStatusCode(), '->render() sends an OK response when If-Modified-Since is stale');
        $this->assertEquals($content, $response->getContent(), '->render() sends the dumped asset as the response content');
    }

    public function testRenderETagFresh()
    {
        $asset = $this->getMock('Assetic\\Asset\\AssetInterface');

        $name = 'foo';
        $formula = array(array('js/core.js'), array(), array(''));
        $etag = md5(serialize($formula + array('last_modified' => null)));

        $asset->expects($this->any())->method('getFilters')->will($this->returnValue(array()));
        $this->am->expects($this->once())->method('has')->with($name)->will($this->returnValue(true));
        $this->am->expects($this->once())->method('get')->with($name)->will($this->returnValue($asset));

        $this->am->expects($this->once())
            ->method('hasFormula')
            ->with($name)
            ->will($this->returnValue(true));
        $this->am->expects($this->once())
            ->method('getFormula')
            ->with($name)
            ->will($this->returnValue($formula));
        $this->request->expects($this->once())
            ->method('getETags')
            ->will($this->returnValue(array('"'.$etag.'"')));
        $asset->expects($this->never())
            ->method('dump');

        $response = $this->controller->render($name);
        $this->assertEquals(304, $response->getStatusCode(), '->render() sends a Not Modified response when If-None-Match is fresh');
    }

    public function testRenderETagStale()
    {
        $asset = $this->getMock('Assetic\\Asset\\AssetInterface');

        $name = 'foo';
        $content = '==ASSET_CONTENT==';
        $formula = array(array('js/core.js'), array(), array(''));
        $etag = md5(serialize($formula + array('last_modified' => null)));

        $asset->expects($this->any())->method('getFilters')->will($this->returnValue(array()));
        $this->am->expects($this->once())->method('has')->with($name)->will($this->returnValue(true));
        $this->am->expects($this->once())->method('get')->with($name)->will($this->returnValue($asset));

        $this->am->expects($this->once())
            ->method('hasFormula')
            ->with($name)
            ->will($this->returnValue(true));
        $this->am->expects($this->once())
            ->method('getFormula')
            ->with($name)
            ->will($this->returnValue($formula));
        $this->request->expects($this->once())
            ->method('getETags')
            ->will($this->returnValue(array('"123"')));
        $asset->expects($this->once())
            ->method('dump')
            ->will($this->returnValue($content));

        $response = $this->controller->render($name);
        $this->assertEquals(200, $response->getStatusCode(), '->render() sends an OK response when If-None-Match is stale');
        $this->assertEquals($content, $response->getContent(), '->render() sends the dumped asset as the response content');
    }
}
