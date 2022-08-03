<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Bar;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\AttributeController;
use Symfony\Component\HttpKernel\Tests\TestHttpKernel;

class ControllerEventTest extends TestCase
{
    public function testSetAttributes()
    {
        $request = new Request();
        $request->attributes->set('_controller_reflectors', [1, 2]);
        $controller = [new AttributeController(), 'action'];
        $event = new ControllerEvent(new TestHttpKernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST);
        $event->setController($controller, []);

        $this->assertSame([], $event->getAttributes());
    }

    /**
     * @dataProvider provideGetAttributes
     */
    public function testGetAttributes(callable $controller)
    {
        $request = new Request();
        $reflector = new \ReflectionFunction($controller(...));
        $request->attributes->set('_controller_reflectors', [str_contains($reflector->name, '{closure}') ? null : $reflector->getClosureScopeClass(), $reflector]);

        $event = new ControllerEvent(new TestHttpKernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        $expected = [
            Bar::class => [
                new Bar('class'),
                new Bar('method'),
            ],
        ];

        $this->assertEquals($expected, $event->getAttributes());
    }

    public function provideGetAttributes()
    {
        yield [[new AttributeController(), '__invoke']];
        yield [new AttributeController()];
        yield [(new AttributeController())->__invoke(...)];
        yield [#[Bar('class'), Bar('method')] static function () {}];
    }
}
