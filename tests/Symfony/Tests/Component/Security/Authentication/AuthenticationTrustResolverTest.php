<?php

namespace Symfony\Tests\Component\Security\Authentication;

use Symfony\Component\Security\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Authentication\AuthenticationTrustResolver;

class AuthenticationTrustResolverTest extends \PHPUnit_Framework_TestCase
{
    public function testIsAnonymous()
    {
        $resolver = $this->getResolver();

        $this->assertFalse($resolver->isAnonymous(null));
        $this->assertFalse($resolver->isAnonymous($this->getToken()));
        $this->assertFalse($resolver->isAnonymous($this->getRememberMeToken()));
        $this->assertTrue($resolver->isAnonymous($this->getAnonymousToken()));
    }

    public function testIsRememberMe()
    {
        $resolver = $this->getResolver();

        $this->assertFalse($resolver->isRememberMe(null));
        $this->assertFalse($resolver->isRememberMe($this->getToken()));
        $this->assertFalse($resolver->isRememberMe($this->getAnonymousToken()));
        $this->assertTrue($resolver->isRememberMe($this->getRememberMeToken()));
    }

    public function testisFullFledged()
    {
        $resolver = $this->getResolver();

        $this->assertFalse($resolver->isFullFledged(null));
        $this->assertFalse($resolver->isFullFledged($this->getAnonymousToken()));
        $this->assertFalse($resolver->isFullFledged($this->getRememberMeToken()));
        $this->assertTrue($resolver->isFullFledged($this->getToken()));
    }

    protected function getToken()
    {
        return $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
    }

    protected function getAnonymousToken()
    {
        return $this->getMock('Symfony\Component\Security\Authentication\Token\AnonymousToken', null, array('', ''));
    }

    protected function getRememberMeToken()
    {
        return $this->getMock('Symfony\Component\Security\Authentication\Token\RememberMeToken', array('setPersistent'), array(), '', false);
    }

    protected function getResolver()
    {
        return new AuthenticationTrustResolver(
            'Symfony\\Component\\Security\\Authentication\\Token\\AnonymousToken',
            'Symfony\\Component\\Security\\Authentication\\Token\\RememberMeToken'
        );
    }
}