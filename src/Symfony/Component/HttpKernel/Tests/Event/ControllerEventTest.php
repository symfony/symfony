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
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Baz;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\AttributeController;
use Symfony\Component\HttpKernel\Tests\TestHttpKernel;

class ControllerEventTest extends TestCase
{
    /**
     * @dataProvider provideGetAttributes
     */
    public function testGetAttributes(callable $controller)
    {
        $event = new ControllerEvent(new TestHttpKernel(), $controller, new Request(), HttpKernelInterface::MAIN_REQUEST);

        $expected = [
            Bar::class => [
                new Bar('class'),
                new Bar('method'),
            ],
            Baz::class => [
                new Baz(),
            ],
        ];

        $this->assertEquals($expected, $event->getAttributes());
    }

    /**
     * @dataProvider provideGetAttributes
     */
    public function testGetAttributesByClassName(callable $controller)
    {
        $event = new ControllerEvent(new TestHttpKernel(), $controller, new Request(), HttpKernelInterface::MAIN_REQUEST);

        $expected = [
            new Bar('class'),
            new Bar('method'),
        ];

        $this->assertEquals($expected, $event->getAttributes(Bar::class));
    }

    /**
     * @dataProvider provideGetAttributes
     */
    public function testGetAttributesByInvalidClassName(callable $controller)
    {
        $event = new ControllerEvent(new TestHttpKernel(), $controller, new Request(), HttpKernelInterface::MAIN_REQUEST);

        $this->assertEquals([], $event->getAttributes(\stdClass::class));
    }

    public static function provideGetAttributes()
    {
        yield [[new AttributeController(), '__invoke']];
        yield [new AttributeController()];
        yield [(new AttributeController())->__invoke(...)];
        yield [#[Bar('class'), Bar('method'), Baz] static function () {}];
    }
}
