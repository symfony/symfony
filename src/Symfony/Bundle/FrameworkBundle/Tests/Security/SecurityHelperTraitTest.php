<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Security\SecurityHelperTrait;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class SecurityHelperTraitTest extends TestCase
{
    public function testDenyAccessUnlessGranted()
    {
        $dummyObject = new \stdClass();
        $authorizationCheckerMock = $this->getMock(AuthorizationCheckerInterface::class);
        $authorizationCheckerMock->expects($this->once())
            ->method('isGranted')
            ->with(array('foo' => 'bar'), $this->identicalTo($dummyObject))
            ->will($this->returnValue(true));

        $securityHelper = new DummySecurityHelper(
            $authorizationCheckerMock,
            $this->getMock(TokenStorageInterface::class),
            $this->getMock(CsrfTokenManagerInterface::class)
        );

        $securityHelper->denyAccessUnlessGranted(array('foo' => 'bar'), $dummyObject, 'Nice try, buddy!');
    }

    public function testDenyAccessUnlessGrantedWithContainer()
    {
        $dummyObject = new \stdClass();
        $authorizationCheckerMock = $this->getMock(AuthorizationCheckerInterface::class);
        $authorizationCheckerMock->expects($this->once())
            ->method('isGranted')
            ->with(array('foo' => 'bar'), $this->identicalTo($dummyObject))
            ->will($this->returnValue(true));

        $container = new Container();
        $container->set('security.authorization_checker', $authorizationCheckerMock);

        $securityHelper = new DummySecurityHelperWithContainer();
        $securityHelper->setContainer($container);

        $securityHelper->denyAccessUnlessGranted(array('foo' => 'bar'), $dummyObject, 'Nice try, buddy!');
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AccessDeniedException
     * @expectedExceptionMessage Nice try, buddy!
     */
    public function testDenyAccessUnlessGrantedThrowsException()
    {
        $dummyObject = new \stdClass();
        $authorizationCheckerMock = $this->getMock(AuthorizationCheckerInterface::class);
        $authorizationCheckerMock->expects($this->once())
            ->method('isGranted')
            ->with(array('foo' => 'bar'), $this->identicalTo($dummyObject))
            ->will($this->returnValue(false));

        $securityHelper = new DummySecurityHelper(
            $authorizationCheckerMock,
            $this->getMock(TokenStorageInterface::class),
            $this->getMock(CsrfTokenManagerInterface::class)
        );

        $securityHelper->denyAccessUnlessGranted(array('foo' => 'bar'), $dummyObject, 'Nice try, buddy!');
    }

    /**
     * @expectedException \Symfony\Bundle\FrameworkBundle\Exception\LogicException
     */
    public function testDenyAccessUnlessGrantedWithMissingDependencies()
    {
        $securityHelper = new DummySecurityHelperWithContainer();
        $securityHelper->setContainer(new Container());

        $securityHelper->denyAccessUnlessGranted(array('foo' => 'bar'), new \stdClass(), 'Nice try, buddy!');
    }

    /**
     * @expectedException \Symfony\Bundle\FrameworkBundle\Exception\LogicException
     */
    public function testDenyAccessUnlessGrantedWithoutContainer()
    {
        $securityHelper = new DummySecurityHelperWithContainer();

        $securityHelper->denyAccessUnlessGranted(array('foo' => 'bar'), new \stdClass(), 'Nice try, buddy!');
    }

    public function testGetUser()
    {
        $user = new User('user', 'pass');
        $token = new UsernamePasswordToken($user, 'pass', 'default', array('ROLE_USER'));

        $securityHelper = new DummySecurityHelper(
            $this->getMock(AuthorizationCheckerInterface::class),
            $this->getAuthenticationTokenStorageMock($token),
            $this->getMock(CsrfTokenManagerInterface::class)
        );

        $this->assertSame($securityHelper->getUser(), $user);
    }

    public function testGetUserWithContainer()
    {
        $user = new User('user', 'pass');
        $token = new UsernamePasswordToken($user, 'pass', 'default', array('ROLE_USER'));
        $container = new Container();
        $container->set('security.token_storage', $this->getAuthenticationTokenStorageMock($token));

        $securityHelper = new DummySecurityHelperWithContainer();
        $securityHelper->setContainer($container);

        $this->assertSame($securityHelper->getUser(), $user);
    }

    public function testGetUserAnonymousUserConvertedToNull()
    {
        $token = new AnonymousToken('default', 'anon.');

        $securityHelper = new DummySecurityHelper(
            $this->getMock(AuthorizationCheckerInterface::class),
            $this->getAuthenticationTokenStorageMock($token),
            $this->getMock(CsrfTokenManagerInterface::class)
        );

        $this->assertNull($securityHelper->getUser());
    }

    public function testGetUserWithEmptyTokenStorage()
    {
        $securityHelper = new DummySecurityHelper(
            $this->getMock(AuthorizationCheckerInterface::class),
            $this->getAuthenticationTokenStorageMock(null),
            $this->getMock(CsrfTokenManagerInterface::class)
        );

        $this->assertNull($securityHelper->getUser());
    }

    /**
     * @expectedException \Symfony\Bundle\FrameworkBundle\Exception\LogicException
     * @expectedExceptionMessage The SecurityBundle is not registered in your application.
     */
    public function testGetUserWithEmptyContainer()
    {
        $securityHelper = new DummySecurityHelperWithContainer();
        $securityHelper->setContainer(new Container());

        $securityHelper->getUser();
    }

    /**
     * @expectedException \Symfony\Bundle\FrameworkBundle\Exception\LogicException
     */
    public function testGetUserWithMissingDependencies()
    {
        $securityHelper = new DummySecurityHelperWithContainer();

        $securityHelper->getUser();
    }

    /**
     * @param TokenInterface|null $token
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|TokenStorageInterface
     */
    private function getAuthenticationTokenStorageMock(TokenInterface $token = null)
    {
        $tokenStorage = $this->getMock(TokenStorageInterface::class);
        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue($token));

        return $tokenStorage;
    }

    public function testIsCsrfTokenValid()
    {
        $csrfTokenManagerMock = $this->getMock(CsrfTokenManagerInterface::class);
        $csrfTokenManagerMock->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('some_id', 'some_token'))
            ->will($this->returnValue(true));

        $securityHelper = new DummySecurityHelper(
            $this->getMock(AuthorizationCheckerInterface::class),
            $this->getMock(TokenStorageInterface::class),
            $csrfTokenManagerMock
        );

        $this->assertTrue($securityHelper->isCsrfTokenValid('some_id', 'some_token'));
    }

    public function testIsCsrfTokenValidWithContainer()
    {
        $csrfTokenManagerMock = $this->getMock(CsrfTokenManagerInterface::class);
        $csrfTokenManagerMock->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('some_id', 'some_token'))
            ->will($this->returnValue(true));

        $container = new Container();
        $container->set('security.csrf.token_manager', $csrfTokenManagerMock);

        $securityHelper = new DummySecurityHelperWithContainer();
        $securityHelper->setContainer($container);

        $this->assertTrue($securityHelper->isCsrfTokenValid('some_id', 'some_token'));
    }

    /**
     * @expectedException \Symfony\Bundle\FrameworkBundle\Exception\LogicException
     */
    public function testIsCsrfTokenValidWithEmptyContainer()
    {
        $securityHelper = new DummySecurityHelperWithContainer();
        $securityHelper->setContainer(new Container());

        $securityHelper->isCsrfTokenValid('some_id', 'some_token');
    }

    /**
     * @expectedException \Symfony\Bundle\FrameworkBundle\Exception\LogicException
     */
    public function testIsCsrfTokenValidWithMissingDependencies()
    {
        $securityHelper = new DummySecurityHelperWithContainer();

        $securityHelper->isCsrfTokenValid('some_id', 'some_token');
    }
}

class DummySecurityHelper
{
    use SecurityHelperTrait {
        denyAccessUnlessGranted as public;
        getUser as public;
        isCsrfTokenValid as public;
    }

    public function __construct(
        AuthorizationCheckerInterface $authorizationChecker,
        TokenStorageInterface $authenticationTokenStorage,
        CsrfTokenManagerInterface $csrfTokenManager
    ) {
        $this->authorizationChecker = $authorizationChecker;
        $this->tokenStorage = $authenticationTokenStorage;
        $this->csrfTokenManager = $csrfTokenManager;
    }
}

class DummySecurityHelperWithContainer implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    use SecurityHelperTrait {
        denyAccessUnlessGranted as public;
        getUser as public;
        isCsrfTokenValid as public;
    }
}
