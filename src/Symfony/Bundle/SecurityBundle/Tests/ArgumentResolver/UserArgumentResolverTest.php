<?php

namespace Symfony\Bundle\SecurityBundle\Tests\ArgumentResolver;

use Symfony\Bundle\SecurityBundle\ArgumentResolver\UserArgumentResolver;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Wouter J <wouter@wouterj.nl>
 */
class UserArgumentResolverTest extends \PHPUnit_Framework_TestCase
{
    private $resolver;
    private $tokenStorage;

    protected function setUp()
    {
        $this->tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $this->resolver = new UserArgumentResolver($this->tokenStorage);
    }

    /**
     * @dataProvider provideClasses
     */
    public function testSupports($class, $supported = true)
    {
        $this->assertEquals($supported, $this->resolver->supports($this->getRequestMock(), $this->getReflectionParameterMock($class)));
    }

    public function provideClasses()
    {
        return array(
            array('Symfony\Component\Security\Core\User\UserInterface'),
            array('Symfony\Component\Security\Core\User\User'),
            array('Symfony\Bundle\SecurityBundle\Tests\ArgumentResolver\UserFixture', false),
            array('\stdClass', false),
        );
    }

    public function testResolvesToNullWhenNoUserIsAvailable()
    {
        $this->tokenStorage->expects($this->any())->method('getToken')->willReturn(null);

        $this->assertNull($this->resolver->resolve($this->getRequestMock(), $this->getReflectionParameterMock()));
    }

    public function testResolvesToNullWhenUserIsAnonymous()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())->method('getUser')->willReturn('anon.');

        $this->tokenStorage->expects($this->any())->method('getToken')->willReturn($token);

        $this->assertNull($this->resolver->resolve($this->getRequestMock(), $this->getReflectionParameterMock()));
    }

    public function testResolvesToUser()
    {
        $user = $this->getMock('Symfony\component\Security\Core\User\User');

        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())->method('getUser')->willReturn($user);

        $this->tokenStorage->expects($this->any())->method('getToken')->willReturn($token);

        $this->assertEquals($user, $this->resolver->resolve($this->getRequestMock(), $this->getReflectionParameterMock()));
    }

    private function getRequestMock()
    {
        return $this->getMock('Symfony\Component\HttpFoundation\Request');
    }

    private function getReflectionParameterMock($class = null)
    {
        $reflectionParameter = $this->getMockBuilder('\ReflectionParameter')->disableOriginalConstructor()->getMock();

        if (null !== $class) {
            $reflectionClass = $this->getMockBuilder('\ReflectionClass')->disableOriginalConstructor()->getMock();
            $reflectionClass->expects($this->any())->method('getName')->willReturn($class);
            $reflectionParameter->expects($this->any())->method('getClass')->willReturn($reflectionClass);
        }

        return $reflectionParameter;
    }
}

class UserFixture implements UserInterface
{
    public function getRoles() {}
    public function getPassword() {}
    public function getSalt() {}
    public function getUsername() {}
    public function eraseCredentials() {}
}
