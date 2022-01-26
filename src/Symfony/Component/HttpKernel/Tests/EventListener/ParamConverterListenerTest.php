<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\ParamConverter;
use Symfony\Component\HttpKernel\Controller\ParamConverter\DateTimeParamConverter;
use Symfony\Component\HttpKernel\Controller\ParamConverter\ParamConverterManager;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\EventListener\ParamConverterListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ParamConverterAttributeController;
use Symfony\Component\HttpKernel\Tests\Fixtures\FooControllerNullableParameter;
use Symfony\Component\HttpKernel\Tests\Fixtures\InvokableControllerWithUnion;

class ParamConverterListenerTest extends TestCase
{
    /**
     * @dataProvider getControllerWithNoArgsFixtures
     */
    public function testRequestIsSkipped($controllerCallable)
    {
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $request = new Request();

        $listener = new ParamConverterListener($this->getParamConverterManager($request, []));
        $event = new ControllerEvent($kernel, $controllerCallable, $request, null);

        $listener->onKernelController($event);
    }

    public function getControllerWithNoArgsFixtures()
    {
        return [
            [[new TestController(), 'noArgAction']],
            [new InvokableNoArgController()],
        ];
    }

    /**
     * @dataProvider getControllerWithArgsFixtures
     */
    public function testAutoConvert($controllerCallable)
    {
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $request = new Request([], [], ['date' => '2014-03-14 09:00:00']);

        $converter = new ParamConverter(name: 'date', class: 'DateTime');

        $listener = new ParamConverterListener($this->getParamConverterManager($request, ['date' => $converter]));
        $event = new ControllerEvent($kernel, $controllerCallable, $request, null);

        $listener->onKernelController($event);
    }

    public function testAutoConvertInterface()
    {
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $request = new Request([], [], ['date' => '2014-03-14 09:00:00']);

        $converter = new ParamConverter(name: 'date', class: 'DateTimeInterface');

        $listener = new ParamConverterListener($this->getParamConverterManager($request, ['date' => $converter]));
        $event = new ControllerEvent($kernel, new InvokableControllerWithInterface(), $request, null);

        $listener->onKernelController($event);
    }

    /**
     * @dataProvider settingOptionalParamProvider
     * @requires PHP 7.1
     */
    public function testSettingOptionalParam($function, $isOptional)
    {
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $request = new Request();

        $converter = new ParamConverter(name: 'param', class: 'DateTime');
        $converter->setIsOptional($isOptional);

        $listener = new ParamConverterListener($this->getParamConverterManager($request, ['param' => $converter]), true);
        $event = new ControllerEvent(
            $kernel,
            [
                new FooControllerNullableParameter(),
                $function,
            ],
            $request,
            null
        );

        $listener->onKernelController($event);
    }

    public function settingOptionalParamProvider()
    {
        return [
            ['requiredParamAction', false],
            ['defaultParamAction', true],
            ['nullableParamAction', true],
        ];
    }

    /**
     * @dataProvider getControllerWithArgsFixtures
     */
    public function testNoAutoConvert($controllerCallable)
    {
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $request = new Request([], [], ['date' => '2014-03-14 09:00:00']);

        $listener = new ParamConverterListener($this->getParamConverterManager($request, []), false);
        $event = new ControllerEvent($kernel, $controllerCallable, $request, null);

        $listener->onKernelController($event);
    }

    public function testAttribute()
    {
        $request = new Request([], [], ['foo' => '2014-03-14 09:00:00', 'bar' => '2014-03-14 09:00:00']);
        $converters = [
            'bar' => new ParamConverter(name: 'bar', class: 'DateTime'),
            'foo' => new ParamConverter(name: 'foo', class: 'DateTime'),
        ];

        $event = new ControllerEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new ParamConverterAttributeController(), 'foo'],
            $request,
            null
        );

        $listener = new ParamConverterListener($this->getParamConverterManager($request, $converters), false);
        $listener->onKernelController($event);
    }

    public function getControllerWithArgsFixtures(): iterable
    {
        yield [[new TestController(), 'dateAction']];
        yield [new InvokableController()];

        if (80000 <= \PHP_VERSION_ID) {
            yield [new InvokableControllerWithUnion()];
        }
    }

    private function getParamConverterManager(Request $request, $configurations)
    {
        $manager = $this->getMockBuilder(ParamConverterManager::class)->getMock();
        $manager
            ->expects($this->once())
            ->method('apply')
            ->with($this->equalTo($request), $this->equalTo($configurations))
        ;

        return $manager;
    }
}

class TestController
{
    public function noArgAction(Request $request)
    {
    }

    public function dateAction(\DateTime $date)
    {
    }
}

class InvokableNoArgController
{
    public function __invoke(Request $request)
    {
    }
}

class InvokableController
{
    public function __invoke(\DateTime $date)
    {
    }
}

class InvokableControllerWithInterface
{
    public function __invoke(\DateTimeInterface $date)
    {
    }
}
