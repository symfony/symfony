<?php

namespace Symfony\Tests\Component\Security\Acl\Domain;

use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;

use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;

use Symfony\Component\Security\Acl\Domain\SecurityIdentityRetrievalStrategy;

class SecurityIdentityRetrievalStrategyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getSecurityIdentityRetrievalTests
     */
    public function testGetSecurityIdentities($user, array $roles, $authenticationStatus, array $sids)
    {
        $strategy = $this->getStrategy($roles, $authenticationStatus);

        $token = $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        $token
            ->expects($this->once())
            ->method('getRoles')
            ->will($this->returnValue(array('foo')))
        ;
        $token
            ->expects($this->once())
            ->method('getUser')
            ->will($this->returnValue($user))
        ;

        $extractedSids = $strategy->getSecurityIdentities($token);

        foreach ($extractedSids as $index => $extractedSid) {
            if (!isset($sids[$index])) {
                $this->fail(sprintf('Expected SID at index %d, but there was none.', true));
            }

            if (false === $sids[$index]->equals($extractedSid)) {
                $this->fail(sprintf('Index: %d, expected SID "%s", but got "%s".', $index, $sids[$index], $extractedSid));
            }
        }
    }

    public function getSecurityIdentityRetrievalTests()
    {
        return array(
            array($this->getAccount('johannes', 'FooUser'), array('ROLE_USER', 'ROLE_SUPERADMIN'), 'fullFledged', array(
                new UserSecurityIdentity('johannes', 'FooUser'),
                new RoleSecurityIdentity('ROLE_USER'),
                new RoleSecurityIdentity('ROLE_SUPERADMIN'),
                new RoleSecurityIdentity('IS_AUTHENTICATED_FULLY'),
                new RoleSecurityIdentity('IS_AUTHENTICATED_REMEMBERED'),
                new RoleSecurityIdentity('IS_AUTHENTICATED_ANONYMOUSLY'),
            )),
            array($this->getAccount('foo', 'FooBarUser'), array('ROLE_FOO'), 'rememberMe', array(
                new UserSecurityIdentity('foo', 'FooBarUser'),
                new RoleSecurityIdentity('ROLE_FOO'),
                new RoleSecurityIdentity('IS_AUTHENTICATED_REMEMBERED'),
                new RoleSecurityIdentity('IS_AUTHENTICATED_ANONYMOUSLY'),
            )),
            array('guest', array('ROLE_FOO'), 'anonymous', array(
                new RoleSecurityIdentity('ROLE_FOO'),
                new RoleSecurityIdentity('IS_AUTHENTICATED_ANONYMOUSLY'),
            ))
        );
    }

    protected function getAccount($username, $class)
    {
        $account = $this->getMock('Symfony\Component\Security\User\AccountInterface', array(), array(), $class);
        $account
            ->expects($this->once())
            ->method('__toString')
            ->will($this->returnValue($username))
        ;

        return $account;
    }

    protected function getStrategy(array $roles = array(), $authenticationStatus = 'fullFledged')
    {
        $roleHierarchy = $this->getMock('Symfony\Component\Security\Role\RoleHierarchyInterface');
        $roleHierarchy
            ->expects($this->once())
            ->method('getReachableRoles')
            ->with($this->equalTo(array('foo')))
            ->will($this->returnValue($roles))
        ;

        $trustResolver = $this->getMock('Symfony\Component\Security\Authentication\AuthenticationTrustResolver', array(), array('', ''));

        $trustResolver
            ->expects($this->at(0))
            ->method('isAnonymous')
            ->will($this->returnValue('anonymous' === $authenticationStatus))
        ;

        if ('fullFledged' === $authenticationStatus) {
            $trustResolver
                ->expects($this->once())
                ->method('isFullFledged')
                ->will($this->returnValue(true))
            ;
            $trustResolver
                ->expects($this->never())
                ->method('isRememberMe')
            ;
        } else if ('rememberMe' === $authenticationStatus) {
            $trustResolver
                ->expects($this->once())
                ->method('isFullFledged')
                ->will($this->returnValue(false))
            ;
            $trustResolver
                ->expects($this->once())
                ->method('isRememberMe')
                ->will($this->returnValue(true))
            ;
        } else {
            $trustResolver
                ->expects($this->at(1))
                ->method('isAnonymous')
                ->will($this->returnValue(true))
            ;
            $trustResolver
                ->expects($this->once())
                ->method('isFullFledged')
                ->will($this->returnValue(false))
            ;
            $trustResolver
                ->expects($this->once())
                ->method('isRememberMe')
                ->will($this->returnValue(false))
            ;
        }


        return new SecurityIdentityRetrievalStrategy($roleHierarchy, $trustResolver);
    }
}