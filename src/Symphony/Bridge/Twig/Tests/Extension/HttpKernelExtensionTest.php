<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symphony\Bridge\Twig\Extension\HttpKernelExtension;
use Symphony\Bridge\Twig\Extension\HttpKernelRuntime;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\Fragment\FragmentHandler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class HttpKernelExtensionTest extends TestCase
{
    /**
     * @expectedException \Twig\Error\RuntimeError
     */
    public function testFragmentWithError()
    {
        $renderer = $this->getFragmentHandler($this->throwException(new \Exception('foo')));

        $this->renderTemplate($renderer);
    }

    public function testRenderFragment()
    {
        $renderer = $this->getFragmentHandler($this->returnValue(new Response('html')));

        $response = $this->renderTemplate($renderer);

        $this->assertEquals('html', $response);
    }

    public function testUnknownFragmentRenderer()
    {
        $context = $this->getMockBuilder('Symphony\\Component\\HttpFoundation\\RequestStack')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $renderer = new FragmentHandler($context);

        if (method_exists($this, 'expectException')) {
            $this->expectException('InvalidArgumentException');
            $this->expectExceptionMessage('The "inline" renderer does not exist.');
        } else {
            $this->setExpectedException('InvalidArgumentException', 'The "inline" renderer does not exist.');
        }

        $renderer->render('/foo');
    }

    protected function getFragmentHandler($return)
    {
        $strategy = $this->getMockBuilder('Symphony\\Component\\HttpKernel\\Fragment\\FragmentRendererInterface')->getMock();
        $strategy->expects($this->once())->method('getName')->will($this->returnValue('inline'));
        $strategy->expects($this->once())->method('render')->will($return);

        $context = $this->getMockBuilder('Symphony\\Component\\HttpFoundation\\RequestStack')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $context->expects($this->any())->method('getCurrentRequest')->will($this->returnValue(Request::create('/')));

        return new FragmentHandler($context, array($strategy), false);
    }

    protected function renderTemplate(FragmentHandler $renderer, $template = '{{ render("foo") }}')
    {
        $loader = new ArrayLoader(array('index' => $template));
        $twig = new Environment($loader, array('debug' => true, 'cache' => false));
        $twig->addExtension(new HttpKernelExtension());

        $loader = $this->getMockBuilder('Twig\RuntimeLoader\RuntimeLoaderInterface')->getMock();
        $loader->expects($this->any())->method('load')->will($this->returnValueMap(array(
            array('Symphony\Bridge\Twig\Extension\HttpKernelRuntime', new HttpKernelRuntime($renderer)),
        )));
        $twig->addRuntimeLoader($loader);

        return $twig->render('index');
    }
}
