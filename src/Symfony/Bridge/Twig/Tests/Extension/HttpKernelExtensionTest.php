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
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;

class HttpKernelExtensionTest extends TestCase
{
    /**
     * @expectedException \Twig_Error_Runtime
     */
    public function testFragmentWithError()
    {
        $kernel = $this->getFragmentHandler($this->throwException(new \Exception('foo')));

        $loader = new \Twig_Loader_Array(array('index' => '{{ fragment("foo") }}'));
        $twig = new \Twig_Environment($loader, array('debug' => true, 'cache' => false));
        $twig->addExtension(new HttpKernelExtension($kernel));

        $this->renderTemplate($kernel);
    }

    protected function getFragmentHandler($return)
    {
        $strategy = $this->getMock('Symfony\\Component\\HttpKernel\\Fragment\\FragmentRendererInterface');
        $strategy->expects($this->once())->method('getName')->will($this->returnValue('inline'));
        $strategy->expects($this->once())->method('render')->will($return);

        $renderer = new FragmentHandler(array($strategy));
        $renderer->setRequest(Request::create('/'));

        return $renderer;
    }

    protected function renderTemplate(FragmentHandler $renderer, $template = '{{ render("foo") }}')
    {
        $loader = new \Twig_Loader_Array(array('index' => $template));
        $twig = new \Twig_Environment($loader, array('debug' => true, 'cache' => false));
        $twig->addExtension(new HttpKernelExtension($renderer));

        return $twig->render('index');
    }
}
