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
use Symfony\Bridge\Twig\TemplateGuesser;
use Symfony\Bridge\Twig\Tests\Fixtures\Controller\TemplateAttributeController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;

class TemplateAttributeListenerTest extends TestCase
{
    public function testAttribute()
    {
        $templateGuesser = $this->getMockBuilder(TemplateGuesser::class)
            ->disableOriginalConstructor()
            ->getMock();
        $twig = $this->getMockBuilder(Environment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Request();
        $event = new ControllerEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new TemplateAttributeController(), 'foo'],
            $request,
            null
        );

        $listener = new TemplateAttributeListener($templateGuesser, $twig);
        $listener->onKernelController($event);

        $configuration = $request->attributes->get('_template');

        $this->assertNotNull($configuration);
        $this->assertEquals('templates/foo.html.twig', $configuration->getTemplate());
        $this->assertEquals(['bar'], $configuration->getVars());
    }

    public function testParameters()
    {
        $templateGuesser = $this->getMockBuilder(TemplateGuesser::class)
            ->disableOriginalConstructor()
            ->getMock();
        $twig = $this->getMockBuilder(Environment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $twig->expects($this->once())
            ->method('render')
            ->with('template.html.twig', ['foo' => 'bar']);

        $request = new Request([], [], [
            '_template' => new Template(template: 'template.html.twig', owner: ['FooController', 'barAction']),
        ]);
        $event = new ViewEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            ['foo' => 'bar']
        );

        $listener = new TemplateAttributeListener($templateGuesser, $twig);
        $listener->onKernelView($event);

        $this->assertEquals([], $request->attributes->get('_template')->getOwner());
    }
}
