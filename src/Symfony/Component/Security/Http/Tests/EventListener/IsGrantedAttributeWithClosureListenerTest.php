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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\ClosureVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\EventListener\IsGrantedAttributeListener;
use Symfony\Component\Security\Http\Tests\Fixtures\IsGrantedAttributeMethodsWithClosureController;
use Symfony\Component\Security\Http\Tests\Fixtures\IsGrantedAttributeWithClosureController;

/**
 * @requires PHP 8.5
 */
class IsGrantedAttributeWithClosureListenerTest extends TestCase
{
    public function testAttribute()
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->exactly(2))
            ->method('isGranted')
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeWithClosureController(), 'foo'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeWithClosureController(), 'bar'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testNothingHappensWithNoConfig()
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->never())
            ->method('isGranted');

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsWithClosureController(), 'noAttribute'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedCalledCorrectly()
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with($this->isInstanceOf(\Closure::class), null)
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsWithClosureController(), 'admin'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedSubjectFromArguments()
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            // the subject => arg2name will eventually resolve to the 2nd argument, which has this value
            ->with($this->isInstanceOf(\Closure::class), 'arg2Value')
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsWithClosureController(), 'withSubject'],
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
            ->with($this->isInstanceOf(\Closure::class), [
                'arg1Name' => 'arg1Value',
                'arg2Name' => 'arg2Value',
            ])
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsWithClosureController(), 'withSubjectArray'],
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
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with($this->isInstanceOf(\Closure::class), null)
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsWithClosureController(), 'withSubject'],
            ['arg1Value', null],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedArrayWithNullValueSubjectFromArguments()
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with($this->isInstanceOf(\Closure::class), [
                'arg1Name' => 'arg1Value',
                'arg2Name' => null,
            ])
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsWithClosureController(), 'withSubjectArray'],
            ['arg1Value', null],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testExceptionWhenMissingSubjectAttribute()
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsWithClosureController(), 'withMissingSubject'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);

        $this->expectException(\RuntimeException::class);

        $listener->onKernelControllerArguments($event);
    }

    /**
     * @dataProvider getAccessDeniedMessageTests
     */
    public function testAccessDeniedMessages(string|array|null $subject, string $method, int $numOfArguments, string $expectedMessage)
    {
        $authChecker = new AuthorizationChecker(new TokenStorage(), new AccessDecisionManager((function () use (&$authChecker) {
            yield new ClosureVoter($authChecker);
        })()));

        // avoid the error of the subject not being found in the request attributes
        $arguments = array_fill(0, $numOfArguments, 'bar');
        $listener = new IsGrantedAttributeListener($authChecker);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsWithClosureController(), $method],
            $arguments,
            new Request(),
            null
        );

        try {
            $listener->onKernelControllerArguments($event);
            $this->fail();
        } catch (AccessDeniedException $e) {
            $this->assertSame($expectedMessage, $e->getMessage());
            $this->assertInstanceOf(\Closure::class, $e->getAttributes()[0]);
            if (null !== $subject) {
                $this->assertSame($subject, $e->getSubject());
            } else {
                $this->assertNull($e->getSubject());
            }
        }
    }

    public static function getAccessDeniedMessageTests()
    {
        yield [null, 'admin', 0, 'Access Denied. Closure {closure:Symfony\Component\Security\Http\Tests\Fixtures\IsGrantedAttributeMethodsWithClosureController::admin():23} returned false.'];
        yield ['bar', 'withSubject', 2, 'Access Denied. Closure {closure:Symfony\Component\Security\Http\Tests\Fixtures\IsGrantedAttributeMethodsWithClosureController::withSubject():30} returned false.'];
        yield [['arg1Name' => 'bar', 'arg2Name' => 'bar'], 'withSubjectArray', 2, 'Access Denied. Closure {closure:Symfony\Component\Security\Http\Tests\Fixtures\IsGrantedAttributeMethodsWithClosureController::withSubjectArray():37} returned false.'];
        yield ['bar', 'withClosureAsSubject', 1, 'Access Denied. Closure {closure:Symfony\Component\Security\Http\Tests\Fixtures\IsGrantedAttributeMethodsWithClosureController::withClosureAsSubject():73} returned false.'];
        yield [['author' => 'bar', 'alias' => 'bar'], 'withNestArgsInSubject', 2, 'Access Denied. Closure {closure:Symfony\Component\Security\Http\Tests\Fixtures\IsGrantedAttributeMethodsWithClosureController::withNestArgsInSubject():85} returned false.'];
    }

    public function testNotFoundHttpException()
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->any())
            ->method('isGranted')
            ->willReturn(false);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsWithClosureController(), 'notFound'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Not found');

        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedWithClosureAsSubject()
    {
        $request = new Request();

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with($this->isInstanceOf(\Closure::class), 'postVal')
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsWithClosureController(), 'withClosureAsSubject'],
            ['postVal'],
            $request,
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedWithNestedExpressionInSubject()
    {
        $request = new Request();

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with($this->isInstanceOf(\Closure::class), ['author' => 'postVal', 'alias' => 'bar'])
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsWithClosureController(), 'withNestArgsInSubject'],
            ['postVal', 'bar'],
            $request,
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testHttpExceptionWithExceptionCode()
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->any())
            ->method('isGranted')
            ->willReturn(false);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsWithClosureController(), 'exceptionCodeInHttpException'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Exception Code');
        $this->expectExceptionCode(10010);

        $listener->onKernelControllerArguments($event);
    }

    public function testAccessDeniedExceptionWithExceptionCode()
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->any())
            ->method('isGranted')
            ->willReturn(false);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsWithClosureController(), 'exceptionCodeInAccessDeniedException'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Exception Code');
        $this->expectExceptionCode(10010);

        $listener->onKernelControllerArguments($event);
    }
}
