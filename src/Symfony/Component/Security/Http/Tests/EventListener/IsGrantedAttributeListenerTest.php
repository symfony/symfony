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
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
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

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
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
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
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
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
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
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
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
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
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
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
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

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);

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
    public function testAccessDeniedMessages(string|Expression $attribute, string|array|null $subject, string $method, int $numOfArguments, string $expectedMessage)
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->any())
            ->method('isGranted')
            ->willReturn(false);

        $expressionLanguage = $this->createMock(ExpressionLanguage::class);
        $expressionLanguage->expects($this->any())
            ->method('evaluate')
            ->willReturn('bar');

        // avoid the error of the subject not being found in the request attributes
        $arguments = array_fill(0, $numOfArguments, 'bar');

        $listener = new IsGrantedAttributeListener($authChecker, $expressionLanguage);

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
            $this->assertEquals([$attribute], $e->getAttributes());
            if (null !== $subject) {
                $this->assertSame($subject, $e->getSubject());
            } else {
                $this->assertNull($e->getSubject());
            }
        }
    }

    public static function getAccessDeniedMessageTests()
    {
        yield ['ROLE_ADMIN', null, 'admin', 0, 'Access Denied by #[IsGranted("ROLE_ADMIN")] on controller'];
        yield ['ROLE_ADMIN', 'bar', 'withSubject', 2, 'Access Denied by #[IsGranted("ROLE_ADMIN", "arg2Name")] on controller'];
        yield ['ROLE_ADMIN', ['arg1Name' => 'bar', 'arg2Name' => 'bar'], 'withSubjectArray', 2, 'Access Denied by #[IsGranted("ROLE_ADMIN", ["arg1Name", "arg2Name"])] on controller'];
        yield [new Expression('"ROLE_ADMIN" in role_names or is_granted("POST_VIEW", subject)'), 'bar', 'withExpressionInAttribute', 1, 'Access Denied by #[IsGranted(new Expression(""ROLE_ADMIN" in role_names or is_granted("POST_VIEW", subject)"), "post")] on controller'];
        yield [new Expression('user === subject'), 'bar', 'withExpressionInSubject', 1, 'Access Denied by #[IsGranted(new Expression("user === subject"), new Expression("args["post"].getAuthor()"))] on controller'];
        yield [new Expression('user === subject["author"]'), ['author' => 'bar', 'alias' => 'bar'], 'withNestedExpressionInSubject', 2, 'Access Denied by #[IsGranted(new Expression("user === subject["author"]"), ["author" => new Expression("args["post"].getAuthor()"), "alias" => "arg2Name"])] on controller'];
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

    public function testIsGrantedWithExpressionInAttribute()
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with(new Expression('"ROLE_ADMIN" in role_names or is_granted("POST_VIEW", subject)'), 'postVal')
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'withExpressionInAttribute'],
            ['postVal'],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedWithExpressionInSubject()
    {
        $request = new Request();

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with(new Expression('user === subject'), 'author')
            ->willReturn(true);

        $expressionLanguage = $this->createMock(ExpressionLanguage::class);
        $expressionLanguage->expects($this->once())
            ->method('evaluate')
            ->with(new Expression('args["post"].getAuthor()'), [
                'args' => ['post' => 'postVal'],
                'request' => $request,
            ])
            ->willReturn('author');

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'withExpressionInSubject'],
            ['postVal'],
            $request,
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker, $expressionLanguage);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedWithNestedExpressionInSubject()
    {
        $request = new Request();

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with(new Expression('user === subject["author"]'), ['author' => 'author', 'alias' => 'arg2Val'])
            ->willReturn(true);

        $expressionLanguage = $this->createMock(ExpressionLanguage::class);
        $expressionLanguage->expects($this->once())
            ->method('evaluate')
            ->with(new Expression('args["post"].getAuthor()'), [
                'args' => ['post' => 'postVal', 'arg2Name' => 'arg2Val'],
                'request' => $request,
            ])
            ->willReturn('author');

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'withNestedExpressionInSubject'],
            ['postVal', 'arg2Val'],
            $request,
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker, $expressionLanguage);
        $listener->onKernelControllerArguments($event);
    }

    public function testIsGrantedWithRequestAsSubject()
    {
        $request = new Request();

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->once())
            ->method('isGranted')
            ->with('SOME_VOTER', $request)
            ->willReturn(true);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'withRequestAsSubject'],
            [],
            $request,
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker, new ExpressionLanguage());
        $listener->onKernelControllerArguments($event);
    }

    public function testHttpExceptionWithExceptionCode()
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Exception Code');
        $this->expectExceptionCode(10010);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->any())
            ->method('isGranted')
            ->willReturn(false);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'exceptionCodeInHttpException'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }

    public function testAccessDeniedExceptionWithExceptionCode()
    {
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Exception Code');
        $this->expectExceptionCode(10010);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects($this->any())
            ->method('isGranted')
            ->willReturn(false);

        $event = new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new IsGrantedAttributeMethodsController(), 'exceptionCodeInAccessDeniedException'],
            [],
            new Request(),
            null
        );

        $listener = new IsGrantedAttributeListener($authChecker);
        $listener->onKernelControllerArguments($event);
    }
}
