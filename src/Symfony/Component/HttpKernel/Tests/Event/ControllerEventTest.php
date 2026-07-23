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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Bar;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Baz;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\AttributeController;
use Symfony\Component\HttpKernel\Tests\TestHttpKernel;

class ControllerEventTest extends TestCase
{
    #[DataProvider('provideGetAttributes')]
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

    #[DataProvider('provideGetAttributes')]
    public function testGetAttributesByClassName(callable $controller)
    {
        $event = new ControllerEvent(new TestHttpKernel(), $controller, new Request(), HttpKernelInterface::MAIN_REQUEST);

        $expected = [
            new Bar('class'),
            new Bar('method'),
        ];

        $this->assertEquals($expected, $event->getAttributes(Bar::class));
    }

    #[DataProvider('provideGetAttributes')]
    public function testGetAttributesByInvalidClassName(callable $controller)
    {
        $event = new ControllerEvent(new TestHttpKernel(), $controller, new Request(), HttpKernelInterface::MAIN_REQUEST);

        $this->assertEquals([], $event->getAttributes(\stdClass::class));
    }

    public function testControllerAttributesAreStoredInRequestAttributes()
    {
        $request = new Request();
        $controller = [new AttributeController(), '__invoke'];
        $event = new ControllerEvent(new TestHttpKernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        // Initially, no controller attributes should be in request
        $this->assertFalse($request->attributes->has('_controller_attributes'));

        // After calling getAttributes(), they should be stored in request attributes
        $attributes = $event->getAttributes();

        $this->assertTrue($request->attributes->has('_controller_attributes'));
        $stored = $request->attributes->get('_controller_attributes');
        $this->assertIsArray($stored);
        $this->assertCount(3, $stored);
        $this->assertIsArray($attributes);
        $this->assertArrayHasKey(Bar::class, $attributes);
    }

    public function testSetControllerWithAttributesStoresInRequest()
    {
        $request = new Request();
        $controller = [new AttributeController(), '__invoke'];
        $event = new ControllerEvent(new TestHttpKernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        // Provide attributes as flat list
        $customAttributes = [new Bar('custom')];

        $event->setController($controller, $customAttributes);

        $stored = $request->attributes->get('_controller_attributes');
        $this->assertIsArray($stored);
        $this->assertCount(1, $stored);
        $this->assertInstanceOf(Bar::class, $stored[0]);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testSetControllerWithGroupedAttributesConvertsToFlat()
    {
        $request = new Request();
        $controller = [new AttributeController(), '__invoke'];
        $event = new ControllerEvent(new TestHttpKernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        $groupedAttributes = [Bar::class => [new Bar('custom')]];

        $event->setController($controller, $groupedAttributes);

        $stored = $request->attributes->get('_controller_attributes');
        $this->assertIsArray($stored);
        $this->assertCount(1, $stored);
        $this->assertInstanceOf(Bar::class, $stored[0]);
    }

    public function testSetControllerWithoutAttributesRemovesFromRequestWhenControllerChanges()
    {
        $request = new Request();
        $controller1 = [new AttributeController(), '__invoke'];
        $controller2 = static fn () => new Response('test');
        $event = new ControllerEvent(new TestHttpKernel(), $controller1, $request, HttpKernelInterface::MAIN_REQUEST);

        // First set some attributes
        $customAttributes = [new Bar('custom')];
        $event->setController($controller1, $customAttributes);
        $this->assertEquals($customAttributes, $request->attributes->get('_controller_attributes'));

        // Then set different controller without attributes - should remove attributes
        $event->setController($controller2);
        $this->assertFalse($request->attributes->has('_controller_attributes'));
    }

    public static function provideGetAttributes()
    {
        yield [[new AttributeController(), '__invoke']];
        yield [new AttributeController()];
        yield [(new AttributeController())->__invoke(...)];
        yield [#[Bar('class'), Bar('method'), Baz] static function () {}];
    }

    public function testEvaluateWithClosureUsesArgsRequestAndController()
    {
        $request = new Request();
        $controller = [new AttributeController(), 'action'];
        $event = new ControllerEvent(new TestHttpKernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        $closure = function (array $args, Request $requestArg, ?object $controllerArg): string {
            $this->assertSame(['baz' => 'value'], $args);
            $this->assertInstanceOf(Request::class, $requestArg);
            $this->assertInstanceOf(AttributeController::class, $controllerArg);

            return 'ok';
        };

        $this->assertSame('ok', $event->evaluate($closure, null, ['baz' => 'value']));
    }

    public function testEvaluateWithExpressionUsesExpressionLanguage()
    {
        $request = new Request();
        $controller = [new AttributeController(), 'action'];
        $event = new ControllerEvent(new TestHttpKernel(), $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        $expressionLanguage = $this->createMock(ExpressionLanguage::class);
        $expressionLanguage->expects($this->once())
            ->method('evaluate')
            ->with(new Expression('args["baz"]'), [
                'request' => $request,
                'args' => ['baz' => 'value'],
                'this' => $controller[0],
            ])
            ->willReturn('value');

        $this->assertSame('value', $event->evaluate(new Expression('args["baz"]'), $expressionLanguage, ['baz' => 'value']));
    }

    public function testEvaluateWithExpressionRequiresExpressionLanguage()
    {
        $event = new ControllerEvent(new TestHttpKernel(), static function () {}, new Request(), HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot evaluate Expression for controllers since no ExpressionLanguage service was configured.');

        $event->evaluate(new Expression('args["foo"]'), null, ['foo' => 'bar']);
    }
}
