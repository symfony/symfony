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
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\Fragment\HIncludeFragmentRenderer;
use Symfony\Component\HttpKernel\UriSigner;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class HIncludeFragmentRendererTest extends TestCase
{
    public function testRenderExceptionWhenControllerAndNoSigner()
    {
        $this->expectException('LogicException');
        $strategy = new HIncludeFragmentRenderer();
        $strategy->render(new ControllerReference('main_controller', [], []), Request::create('/'));
    }

    public function testRenderWithControllerAndSigner()
    {
        $strategy = new HIncludeFragmentRenderer(null, new UriSigner('foo'));

        $this->assertEquals('<hx:include src="/_fragment?_hash=BP%2BOzCD5MRUI%2BHJpgPDOmoju00FnzLhP3TGcSHbbBLs%3D&amp;_path=_format%3Dhtml%26_locale%3Den%26_controller%3Dmain_controller"></hx:include>', $strategy->render(new ControllerReference('main_controller', [], []), Request::create('/'))->getContent());
    }

    public function testRenderWithUri()
    {
        $strategy = new HIncludeFragmentRenderer();
        $this->assertEquals('<hx:include src="/foo"></hx:include>', $strategy->render('/foo', Request::create('/'))->getContent());

        $strategy = new HIncludeFragmentRenderer(null, new UriSigner('foo'));
        $this->assertEquals('<hx:include src="/foo"></hx:include>', $strategy->render('/foo', Request::create('/'))->getContent());
    }

    public function testRenderWithDefault()
    {
        // only default
        $strategy = new HIncludeFragmentRenderer();
        $this->assertEquals('<hx:include src="/foo">default</hx:include>', $strategy->render('/foo', Request::create('/'), ['default' => 'default'])->getContent());

        // only global default
        $strategy = new HIncludeFragmentRenderer(null, null, 'global_default');
        $this->assertEquals('<hx:include src="/foo">global_default</hx:include>', $strategy->render('/foo', Request::create('/'), [])->getContent());

        // global default and default
        $strategy = new HIncludeFragmentRenderer(null, null, 'global_default');
        $this->assertEquals('<hx:include src="/foo">default</hx:include>', $strategy->render('/foo', Request::create('/'), ['default' => 'default'])->getContent());
    }

    public function testRenderWithAttributesOptions()
    {
        // with id
        $strategy = new HIncludeFragmentRenderer();
        $this->assertEquals('<hx:include src="/foo" id="bar">default</hx:include>', $strategy->render('/foo', Request::create('/'), ['default' => 'default', 'id' => 'bar'])->getContent());

        // with attributes
        $strategy = new HIncludeFragmentRenderer();
        $this->assertEquals('<hx:include src="/foo" p1="v1" p2="v2">default</hx:include>', $strategy->render('/foo', Request::create('/'), ['default' => 'default', 'attributes' => ['p1' => 'v1', 'p2' => 'v2']])->getContent());

        // with id & attributes
        $strategy = new HIncludeFragmentRenderer();
        $this->assertEquals('<hx:include src="/foo" p1="v1" p2="v2" id="bar">default</hx:include>', $strategy->render('/foo', Request::create('/'), ['default' => 'default', 'id' => 'bar', 'attributes' => ['p1' => 'v1', 'p2' => 'v2']])->getContent());
    }

    public function testRenderWithTwigAndDefaultText()
    {
        $twig = new Environment($loader = new ArrayLoader());
        $strategy = new HIncludeFragmentRenderer($twig);
        $this->assertEquals('<hx:include src="/foo">loading...</hx:include>', $strategy->render('/foo', Request::create('/'), ['default' => 'loading...'])->getContent());
    }

    /**
     * @group legacy
     */
    public function testRenderWithDefaultTextLegacy()
    {
        $engine = $this->getMockBuilder('Symfony\\Component\\Templating\\EngineInterface')->getMock();
        $engine->expects($this->once())
            ->method('exists')
            ->with('default')
            ->willThrowException(new \InvalidArgumentException());

        // only default
        $strategy = new HIncludeFragmentRenderer($engine);
        $this->assertEquals('<hx:include src="/foo">default</hx:include>', $strategy->render('/foo', Request::create('/'), ['default' => 'default'])->getContent());
    }
}
