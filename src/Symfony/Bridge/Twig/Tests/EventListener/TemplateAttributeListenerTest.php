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
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsMetadata;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

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
        $kernel = $this->createStub(HttpKernelInterface::class);
        $controllerEvent = new ControllerEvent($kernel, [new TemplateAttributeController(), 'foo'], $request, HttpKernelInterface::MAIN_REQUEST);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, $controllerEvent, ['Bar'], $request, null);
        $controllerMetadata = class_exists(ControllerArgumentsMetadata::class) ? new ControllerArgumentsMetadata($controllerEvent, $controllerArgumentsEvent) : $controllerArgumentsEvent;
        $listener = new TemplateAttributeListener($twig);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, ['foo' => 'bar'], $controllerMetadata);
        $listener->onKernelView($event);
        $this->assertSame('Bar', $event->getResponse()->getContent());

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, null, $controllerMetadata);
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

    public function testAttributeWithBlock()
    {
        $twig = new Environment(new ArrayLoader([
            'foo.html.twig' => 'ERROR {% block bar %}FOOBAR{% endblock %}',
        ]));

        $request = new Request();
        $kernel = $this->createStub(HttpKernelInterface::class);
        $controllerEvent = new ControllerEvent($kernel, [new TemplateAttributeController(), 'foo'], $request, HttpKernelInterface::MAIN_REQUEST);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, $controllerEvent, ['Bar'], $request, null);
        $controllerMetadata = class_exists(ControllerArgumentsMetadata::class) ? new ControllerArgumentsMetadata($controllerEvent, $controllerArgumentsEvent) : $controllerArgumentsEvent;
        $listener = new TemplateAttributeListener($twig);

        $request->attributes->set('_template', new Template('foo.html.twig', [], false, 'bar'));
        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, ['foo' => 'bar'], $controllerMetadata);
        $listener->onKernelView($event);
        $this->assertSame('FOOBAR', $event->getResponse()->getContent());

        $request->attributes->set('_template', new Template('foo.html.twig', [], true, 'bar'));
        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, ['foo' => 'bar'], $controllerMetadata);
        $listener->onKernelView($event);
        $this->assertInstanceOf(StreamedResponse::class, $event->getResponse());

        $request->attributes->set('_template', new Template('foo.html.twig', [], false, 'not_a_block'));
        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, ['foo' => 'bar'], $controllerMetadata);
        $this->expectExceptionMessage('Block "not_a_block" on template "foo.html.twig" does not exist in "foo.html.twig".');
        $listener->onKernelView($event);
    }

    public function testForm()
    {
        $request = new Request();
        $kernel = $this->createStub(HttpKernelInterface::class);
        $controllerEvent = new ControllerEvent($kernel, [new TemplateAttributeController(), 'foo'], $request, HttpKernelInterface::MAIN_REQUEST);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, $controllerEvent, [], $request, null);
        $controllerMetadata = class_exists(ControllerArgumentsMetadata::class) ? new ControllerArgumentsMetadata($controllerEvent, $controllerArgumentsEvent) : $controllerArgumentsEvent;
        $listener = new TemplateAttributeListener(new Environment(new ArrayLoader([
            'templates/foo.html.twig' => '',
        ])));

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('createView');
        $form->expects($this->once())->method('isSubmitted')->willReturn(true);
        $form->expects($this->once())->method('isValid')->willReturn(false);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, ['bar' => $form], $controllerMetadata);
        $listener->onKernelView($event);

        $this->assertSame(422, $event->getResponse()->getStatusCode());
    }
}
