<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentNameConverter;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\EventListener\IsGrantedAttributeListener;
use Symfony\Component\Security\Http\Tests\Fixtures\Controller\IsGrantedAttributeController;
use Symfony\Component\Security\Http\Tests\Fixtures\Controller\IsGrantedAttributeMethodsController;

class IsGrantedAttributeListenerTest extends TestCase
{
    public function testAttribute()
    {
        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authChecker->expects($this->exactly(2))
            ->method('isGranted')
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new IsGrantedAttributeController(), 'foo'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($this->createArgumentNameConverter([]), $authChecker);
        $listener->onKernelControllerArguments($event);

        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new IsGrantedAttributeController(), 'bar'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($this->createArgumentNameConverter([]), $authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testExceptionIfSecurityNotInstalled()
    {
        $this->expectException(\LogicException::class);

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new IsGrantedAttributeMethodsController(), 'emptyAttribute'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($this->createArgumentNameConverter([]));
        $listener->onKernelControllerArguments($event);
    }

    public function testNothingHappensWithNoConfig()
    {
        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authChecker->expects($this->never())
            ->method('isGranted');

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new IsGrantedAttributeMethodsController(), 'noAttribute'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($this->createArgumentNameConverter([]), $authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedCalledCorrectly()
    {
        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new IsGrantedAttributeMethodsController(), 'admin'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($this->createArgumentNameConverter([]), $authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedSubjectFromArguments()
    {
        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authChecker->expects($this->once())
            ->method('isGranted')
            // the subject => arg2name will eventually resolve to the 2nd argument, which has this value
            ->with('ROLE_ADMIN', 'arg2Value')
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new IsGrantedAttributeMethodsController(), 'withSubject'],
            [],
            new Request(),
            null
        );

        // create metadata for 2 named args for the controller
        $listener = new IsGrantedAttributeListener($this->createArgumentNameConverter(['arg1Name' => 'arg1Value', 'arg2Name' => 'arg2Value']), $authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedSubjectFromArgumentsWithArray()
    {
        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authChecker->expects($this->once())
            ->method('isGranted')
            // the subject => arg2name will eventually resolve to the 2nd argument, which has this value
            ->with('ROLE_ADMIN', [
                'arg1Name' => 'arg1Value',
                'arg2Name' => 'arg2Value',
            ])
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new IsGrantedAttributeMethodsController(), 'withSubjectArray'],
            [],
            new Request(),
            null
        );

        // create metadata for 2 named args for the controller
        $listener = new IsGrantedAttributeListener($this->createArgumentNameConverter(['arg1Name' => 'arg1Value', 'arg2Name' => 'arg2Value']), $authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedNullSubjectFromArguments()
    {
        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_ADMIN', null)
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new IsGrantedAttributeMethodsController(), 'withSubject'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($this->createArgumentNameConverter(['arg1Name' => 'arg1Value', 'arg2Name' => null]), $authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedArrayWithNullValueSubjectFromArguments()
    {
        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with('ROLE_ADMIN', [
                'arg1Name' => 'arg1Value',
                'arg2Name' => null,
            ])
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new IsGrantedAttributeMethodsController(), 'withSubjectArray'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($this->createArgumentNameConverter(['arg1Name' => 'arg1Value', 'arg2Name' => null]), $authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testExceptionWhenMissingSubjectAttribute()
    {
        $this->expectException(\RuntimeException::class);

        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new IsGrantedAttributeMethodsController(), 'withMissingSubject'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($this->createArgumentNameConverter([]), $authChecker);
        $listener->onKernelControllerArguments($event);
    }

    /**
     * @dataProvider getAccessDeniedMessageTests
     */
    public function testAccessDeniedMessages(array $attributes, ?string $subject, string $method, string $expectedMessage)
    {
        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authChecker->expects($this->any())
            ->method('isGranted')
            ->willReturn(false);

        // avoid the error of the subject not being found in the request attributes
        $arguments = [];
        if (null !== $subject) {
            $arguments[$subject] = 'bar';
        }

        $listener = new IsGrantedAttributeListener($this->createArgumentNameConverter($arguments), $authChecker);

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new IsGrantedAttributeMethodsController(), $method],
            [],
            new Request(),
            null
        );

        try {
            $listener->onKernelControllerArguments($event);
            $this->fail();
        } catch (\Exception $e) {
            $this->assertEquals(AccessDeniedException::class, \get_class($e));
            $this->assertEquals($expectedMessage, $e->getMessage());
            $this->assertEquals($attributes, $e->getAttributes());
            if (null !== $subject) {
                $this->assertEquals('bar', $e->getSubject());
            } else {
                $this->assertNull($e->getSubject());
            }
        }
    }

    public function getAccessDeniedMessageTests()
    {
        yield [['ROLE_ADMIN'], null, 'admin', 'Access Denied by controller annotation @IsGranted("ROLE_ADMIN")'];
        yield [['ROLE_ADMIN', 'ROLE_USER'], null, 'adminOrUser', 'Access Denied by controller annotation @IsGranted(["ROLE_ADMIN", "ROLE_USER"])'];
        yield [['ROLE_ADMIN', 'ROLE_USER'], 'product', 'adminOrUserWithSubject', 'Access Denied by controller annotation @IsGranted(["ROLE_ADMIN", "ROLE_USER"], product)'];
    }

    public function testNotFoundHttpException()
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Not found');

        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authChecker->expects($this->any())
            ->method('isGranted')
            ->willReturn(false);

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new IsGrantedAttributeMethodsController(), 'notFound'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($this->createArgumentNameConverter([]), $authChecker);
        $listener->onKernelControllerArguments($event);
    }

    private function createArgumentNameConverter(array $arguments)
    {
        $nameConverter = $this->getMockBuilder(ArgumentNameConverter::class)->disableOriginalConstructor()->getMock();

        $nameConverter->expects($this->any())
            ->method('getControllerArguments')
            ->willReturn($arguments);

        return $nameConverter;
    }
}
