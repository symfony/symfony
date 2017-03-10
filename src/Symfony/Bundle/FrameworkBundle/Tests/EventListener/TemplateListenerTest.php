<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\EventListener;

use Symfony\Bundle\FrameworkBundle\EventListener\TwigTemplateListener;
use Symfony\Bundle\FrameworkBundle\Templating\TemplatedResponse;
use Symfony\Bundle\FrameworkBundle\Templating\TemplatedResponseInterface;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Kernel;

class TemplateListenerTest extends TestCase
{
    public function testTemplateReference()
    {
        $template = new TemplatedResponse('dummy_template.html.php', array('var' => 'dummy'));

        $event = $this->getEvent($template);

        $listener = new TwigTemplateListener($this->getMockBuilder(\Twig_Environment::class)->getMock());
        $listener->onView($event);

        $response = $event->getResponse();

        $this->assertSame('This is dummy content', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testInvalidResponse()
    {
        $twig = $this->getMockBuilder(\Twig_Environment::class)->getMock();

        $template = $this->getMockBuilder(TemplatedResponseInterface::class)->getMock();
        $template->expects($this->once())
            ->method('getResponse')
            ->with($twig)
            ->will($this->throwException(new \LogicException()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('LogicException');

        $event = $this->getEvent($template);

        $listener = new TwigTemplateListener($twig);
        $listener->onView($event);
    }

    private function getEvent($template)
    {
        $request = new Request(array(), array(), array());
        $mockKernel = $this->getMockForAbstractClass('Symfony\Component\HttpKernel\Kernel', array('', ''));

        return new GetResponseForControllerResultEvent($mockKernel, $request, Kernel::MASTER_REQUEST, $template);
    }
}
