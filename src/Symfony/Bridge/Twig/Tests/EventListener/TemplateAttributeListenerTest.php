<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bridge\Twig\EventListener\TemplateAttributeListener;
use Symfony\Bridge\Twig\Tests\Fixtures\TemplateAttributeController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;

class TemplateAttributeListenerTest extends TestCase
{
    public function testAttribute()
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->exactly(3))
            ->method('render')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    ['templates/foo.html.twig', ['foo' => 'bar']],
                    ['templates/foo.html.twig', ['bar' => 'Bar', 'buz' => 'def']],
                    ['templates/foo.html.twig', []],
                ];

                $this->assertSame(array_shift($series), $args);

                return 'Bar';
            })
        ;

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, [new TemplateAttributeController(), 'foo'], ['Bar'], $request, null);
        $listener = new TemplateAttributeListener($twig);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, ['foo' => 'bar'], $controllerArgumentsEvent);
        $listener->onKernelView($event);
        $this->assertSame('Bar', $event->getResponse()->getContent());

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, null, $controllerArgumentsEvent);
        $listener->onKernelView($event);
        $this->assertSame('Bar', $event->getResponse()->getContent());

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, null);
        $listener->onKernelView($event);
        $this->assertNull($event->getResponse());

        $request->attributes->set('_template', new Template('templates/foo.html.twig'));
        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, []);
        $listener->onKernelView($event);
        $this->assertSame('Bar', $event->getResponse()->getContent());
    }

    public function testForm()
    {
        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, [new TemplateAttributeController(), 'foo'], [], $request, null);
        $listener = new TemplateAttributeListener($this->createMock(Environment::class));

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('createView');
        $form->expects($this->once())->method('isSubmitted')->willReturn(true);
        $form->expects($this->once())->method('isValid')->willReturn(false);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, ['bar' => $form], $controllerArgumentsEvent);
        $listener->onKernelView($event);

        $this->assertSame(422, $event->getResponse()->getStatusCode());
    }
}
