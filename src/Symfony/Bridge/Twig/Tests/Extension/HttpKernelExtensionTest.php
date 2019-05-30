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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\HttpKernelExtension;
use Symfony\Bridge\Twig\Extension\HttpKernelRuntime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;
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
        $context = $this->getMockBuilder('Symfony\\Component\\HttpFoundation\\RequestStack')
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
        $strategy = $this->getMockBuilder('Symfony\\Component\\HttpKernel\\Fragment\\FragmentRendererInterface')->getMock();
        $strategy->expects($this->once())->method('getName')->willReturn('inline');
        $strategy->expects($this->once())->method('render')->will($return);

        $context = $this->getMockBuilder('Symfony\\Component\\HttpFoundation\\RequestStack')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $context->expects($this->any())->method('getCurrentRequest')->willReturn(Request::create('/'));

        return new FragmentHandler($context, [$strategy], false);
    }

    protected function renderTemplate(FragmentHandler $renderer, $template = '{{ render("foo") }}')
    {
        $loader = new ArrayLoader(['index' => $template]);
        $twig = new Environment($loader, ['debug' => true, 'cache' => false]);
        $twig->addExtension(new HttpKernelExtension());

        $loader = $this->getMockBuilder('Twig\RuntimeLoader\RuntimeLoaderInterface')->getMock();
        $loader->expects($this->any())->method('load')->willReturnMap([
            ['Symfony\Bridge\Twig\Extension\HttpKernelRuntime', new HttpKernelRuntime($renderer)],
        ]);
        $twig->addRuntimeLoader($loader);

        return $twig->render('index');
    }
}
