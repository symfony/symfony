<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

class AuthorizationCheckerTest extends TestCase
{
    private $authenticationManager;
    private $accessDecisionManager;
    private $authorizationChecker;
    private $tokenStorage;

    protected function setUp()
    {
        $this->authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();
        $this->accessDecisionManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface')->getMock();
        $this->tokenStorage = new TokenStorage();

        $this->authorizationChecker = new AuthorizationChecker(
            $this->tokenStorage,
            $this->authenticationManager,
            $this->accessDecisionManager
        );
    }

    public function testVoteAuthenticatesTokenIfNecessary()
    {
        $token = new UsernamePasswordToken('username', 'password', 'provider');
        $this->tokenStorage->setToken($token);

        $newToken = new UsernamePasswordToken('username', 'password', 'provider');

        $this->authenticationManager
            ->expects($this->once())
            ->method('authenticate')
            ->with($this->equalTo($token))
            ->willReturn($newToken);

        // default with() isn't a strict check
        $tokenComparison = function ($value) use ($newToken) {
            // make sure that the new token is used in "decide()" and not the old one
            return $value === $newToken;
        };

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($this->callback($tokenComparison))
            ->willReturn(true);

        // first run the token has not been re-authenticated yet, after isGranted is called, it should be equal
        $this->assertNotSame($newToken, $this->tokenStorage->getToken());
        $this->assertTrue($this->authorizationChecker->isGranted('foo'));
        $this->assertSame($newToken, $this->tokenStorage->getToken());
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException
     */
    public function testVoteWithoutAuthenticationToken()
    {
        $this->authorizationChecker->isGranted('ROLE_FOO');
    }

    /**
     * @dataProvider isGrantedProvider
     */
    public function testIsGranted($decide)
    {
        $token = new UsernamePasswordToken('username', 'password', 'provider', ['ROLE_USER']);

        $this->accessDecisionManager
            ->expects($this->once())
            ->method('decide')
            ->willReturn($decide);
        $this->tokenStorage->setToken($token);
        $this->assertSame($decide, $this->authorizationChecker->isGranted('ROLE_FOO'));
    }

    public function isGrantedProvider()
    {
        return [[true], [false]];
    }
}
