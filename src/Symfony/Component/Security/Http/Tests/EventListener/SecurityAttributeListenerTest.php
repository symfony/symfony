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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentNameConverter;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Http\Attribute\Security;
use Symfony\Component\Security\Http\EventListener\SecurityAttributeListener;
use Symfony\Component\Security\Http\Tests\Fixtures\Controller\SecurityAttributeController;

class SecurityAttributeListenerTest extends \PHPUnit\Framework\TestCase
{
    public function testAccessDenied()
    {
        $this->expectException(AccessDeniedException::class);

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new SecurityAttributeController(), 'accessDenied'],
            [],
            new Request(),
            null
        );

        $this->getListener()->onKernelControllerArguments($event);
    }

    public function testNotFoundHttpException()
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Not found');

        $event = new ControllerArgumentsEvent(
            $this->getMockBuilder(HttpKernelInterface::class)->getMock(),
            [new SecurityAttributeController(), 'notFound'],
            [],
            new Request(),
            null
        );

        $this->getListener()->onKernelControllerArguments($event);
    }

    private function getListener()
    {
        $roleHierarchy = $this->getMockBuilder(RoleHierarchy::class)->disableOriginalConstructor()->getMock();
        $roleHierarchy->expects($this->once())->method('getReachableRoleNames')->willReturn([]);

        $token = $this->getMockBuilder(AbstractToken::class)->getMock();
        $token->expects($this->once())->method('getRoleNames')->willReturn([]);

        $tokenStorage = $this->getMockBuilder(TokenStorageInterface::class)->getMock();
        $tokenStorage->expects($this->exactly(2))->method('getToken')->willReturn($token);

        $authChecker = $this->getMockBuilder(AuthorizationCheckerInterface::class)->getMock();
        $authChecker->expects($this->exactly(2))->method('isGranted')->willReturn(false);

        $trustResolver = $this->getMockBuilder(AuthenticationTrustResolverInterface::class)->getMock();

        $argNameConverter = $this->createArgumentNameConverter([]);

        $language = new ExpressionLanguage();

        return new SecurityAttributeListener($argNameConverter, $language, $trustResolver, $roleHierarchy, $tokenStorage, $authChecker);
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
