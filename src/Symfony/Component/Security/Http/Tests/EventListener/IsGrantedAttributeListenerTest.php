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
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\EventListener\IsGrantedAttributeListener;
use Symfony\Component\Security\Http\Tests\Fixtures\IsGrantedAttributeController;
use Symfony\Component\Security\Http\Tests\Fixtures\IsGrantedAttributeMethodsController;

class IsGrantedAttributeListenerTest extends TestCase
{
    public function testAttribute()
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->exactly(2))
            ->method('isGranted')
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeController(), 'foo'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);

        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeController(), 'bar'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testNothingHappensWithNoConfig()
    {
        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authChecker->expects($this->never())
            ->method('isGranted');

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'noAttribute'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
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
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'admin'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
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
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'withSubject'],
            ['arg1Value', 'arg2Value'],
            new Request(),
            null
        );

        // create metadata for 2 named args for the controller
        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedSubjectFromArgumentsWithArray()
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            // the subject => arg2name will eventually resolve to the 2nd argument, which has this value
            ->with('ROLE_ADMIN', [
                'arg1Name' => 'arg1Value',
                'arg2Name' => 'arg2Value',
            ])
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'withSubjectArray'],
            ['arg1Value', 'arg2Value'],
            new Request(),
            null
        );

        // create metadata for 2 named args for the controller
        $listener = new IsGrantedAttributeListener($authChecker);
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
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'withSubject'],
            ['arg1Value', null],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
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
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'withSubjectArray'],
            ['arg1Value', null],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testExceptionWhenMissingSubjectAttribute()
    {
        $this->expectException(\RuntimeException::class);

        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'withMissingSubject'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
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
            $arguments[] = 'bar';
        }

        $listener = new IsGrantedAttributeListener($authChecker);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), $method],
            $arguments,
            new Request(),
            null
        );

        try {
            $listener->onKernelControllerArguments($event);
            $this->fail();
        } catch (AccessDeniedException $e) {
            $this->assertSame($expectedMessage, $e->getMessage());
            $this->assertSame($attributes, $e->getAttributes());
            if (null !== $subject) {
                $this->assertSame('bar', $e->getSubject());
            } else {
                $this->assertNull($e->getSubject());
            }
        }
    }

    public function getAccessDeniedMessageTests()
    {
        yield [['ROLE_ADMIN'], null, 'admin', 'Access Denied by #[IsGranted("ROLE_ADMIN")] on controller'];
        yield [['ROLE_ADMIN', 'ROLE_USER'], null, 'adminOrUser', 'Access Denied by #[IsGranted(["ROLE_ADMIN", "ROLE_USER"])] on controller'];
        yield [['ROLE_ADMIN', 'ROLE_USER'], 'product', 'adminOrUserWithSubject', 'Access Denied by #[IsGranted(["ROLE_ADMIN", "ROLE_USER"], "product")] on controller'];
    }

    public function testNotFoundHttpException()
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Not found');

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->any())
            ->method('isGranted')
            ->willReturn(false);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'notFound'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }
}
