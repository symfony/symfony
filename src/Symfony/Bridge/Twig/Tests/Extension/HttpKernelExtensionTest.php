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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

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

    public function testRenderWithoutMasterRequest()
    {
        $kernel = $this->getKernel($this->returnValue(new Response('foo')));

        $this->assertEquals('foo', $this->renderTemplate($kernel));
    }

    /**
     * @expectedException \Twig_Error_Runtime
     */
    public function testRenderWithError()
    {
        $kernel = $this->getKernel($this->throwException(new \Exception('foo')));

        $loader = new \Twig_Loader_Array(array('index' => '{{ render("foo") }}'));
        $twig = new \Twig_Environment($loader, array('debug' => true, 'cache' => false));
        $twig->addExtension(new HttpKernelExtension($kernel));

        $this->renderTemplate($kernel);
    }

    protected function getKernel($return)
    {
        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $kernel
            ->expects($this->once())
            ->method('handle')
            ->will($return)
        ;

        return $kernel;
    }

    protected function renderTemplate(HttpKernelInterface $kernel, $template = '{{ render("foo") }}')
    {
        $loader = new \Twig_Loader_Array(array('index' => $template));
        $twig = new \Twig_Environment($loader, array('debug' => true, 'cache' => false));
        $twig->addExtension(new HttpKernelExtension($kernel));

        return $twig->render('index');
    }
}
