<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\HttpKernelExtension;
use Symfony\Bridge\Twig\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpContentRenderer;

class HttpKernelExtensionTest extends TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\HttpKernel\HttpKernel')) {
            $this->markTestSkipped('The "HttpKernel" component is not available');
        }

        if (!class_exists('Twig_Environment')) {
            $this->markTestSkipped('Twig is not available.');
        }
    }

    /**
     * @expectedException \Twig_Error_Runtime
     */
    public function testRenderWithError()
    {
        $kernel = $this->getHttpContentRenderer($this->throwException(new \Exception('foo')));

        $loader = new \Twig_Loader_Array(array('index' => '{{ render("foo") }}'));
        $twig = new \Twig_Environment($loader, array('debug' => true, 'cache' => false));
        $twig->addExtension(new HttpKernelExtension($kernel));

        $this->renderTemplate($kernel);
    }

    protected function getHttpContentRenderer($return)
    {
        $strategy = $this->getMock('Symfony\\Component\\HttpKernel\\RenderingStrategy\\RenderingStrategyInterface');
        $strategy->expects($this->once())->method('getName')->will($this->returnValue('default'));
        $strategy->expects($this->once())->method('render')->will($return);

        // simulate a master request
        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')->disableOriginalConstructor()->getMock();
        $event
            ->expects($this->once())
            ->method('getRequest')
            ->will($this->returnValue(Request::create('/')))
        ;

        $renderer = new HttpContentRenderer(array($strategy));
        $renderer->onKernelRequest($event);

        return $renderer;
    }

    protected function renderTemplate(HttpContentRenderer $renderer, $template = '{{ render("foo") }}')
    {
        $loader = new \Twig_Loader_Array(array('index' => $template));
        $twig = new \Twig_Environment($loader, array('debug' => true, 'cache' => false));
        $twig->addExtension(new HttpKernelExtension($renderer));

        return $twig->render('index');
    }
}
