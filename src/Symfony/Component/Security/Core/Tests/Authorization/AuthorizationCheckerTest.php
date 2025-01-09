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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

class AuthorizationCheckerTest extends TestCase
{
    private TokenStorage $tokenStorage;

    protected function setUp(): void
    {
        $this->tokenStorage = new TokenStorage();
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testVoteWithoutAuthenticationToken($useVoteObject, $decideFunction, $voteFunction, $excpectedCallback)
    {
        $accessDecisionManager = $this->createAccessDecisionManagerMock($useVoteObject);

        $authorizationChecker = new AuthorizationChecker($this->tokenStorage, $accessDecisionManager);

        $accessDecisionManager->expects($this->once())
            ->method($decideFunction)
            ->with($this->isInstanceOf(NullToken::class))
            ->willReturn($useVoteObject ? new AccessDecision(VoterInterface::ACCESS_DENIED) : false);

        $authorizationChecker->isGranted('ROLE_FOO');
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testIsGranted($useVoteObject = null, $decideFunction = null, $voteFunction = null, $excpectedCallback=null)
    {
        foreach([true, false] as $decision) {
            $accessDecisionManager = $this->createAccessDecisionManagerMock($useVoteObject);
            $authorizationChecker = new AuthorizationChecker($this->tokenStorage, $accessDecisionManager);

            $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

            $accessDecisionManager
                ->expects($this->once())
                ->method($decideFunction)
                ->willReturn($useVoteObject ? new AccessDecision($decision ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED) : $decision);
            $this->tokenStorage->setToken($token);
            $this->assertSame($decision, $authorizationChecker->isGranted('ROLE_FOO'));
        }
    }

    public static function isGrantedProvider()
    {
        return [[true], [false]];
    }

    public function provideDataWithAndWithoutVoteObject()
    {
        yield [
            'useVoteObject' => false,
            'decideFunction' => 'decide',
            'voteFunction' => 'vote',
            'excpectedCallback' => fn ($a) => $a,
        ];

        yield [
            'useVoteObject' => true,
            'decideFunction' => 'getDecision',
            'voteFunction' => 'getVote',
            'excpectedCallback' => fn ($access, $votes = []) => new AccessDecision(
                $access ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED,
                $votes
            ),
        ];
    }

    /**
     * @dataProvider provideDataWithAndWithoutVoteObject
     */
    public function testIsGrantedWithObjectAttribute($useVoteObject, $decideFunction, $voteFunction, $excpectedCallback)
    {
        $accessDecisionManager = $this->createAccessDecisionManagerMock($useVoteObject);
        $authorizationChecker = new AuthorizationChecker($this->tokenStorage, $accessDecisionManager);

        $attribute = new \stdClass();

        $token = new UsernamePasswordToken(new InMemoryUser('username', 'password', ['ROLE_USER']), 'provider', ['ROLE_USER']);

        $accessDecisionManager
            ->expects($this->once())
            ->method($decideFunction)
            ->with($this->identicalTo($token), $this->identicalTo([$attribute]))
            ->willReturn($useVoteObject ? new AccessDecision(VoterInterface::ACCESS_GRANTED) : true);
        $this->tokenStorage->setToken($token);
        $this->assertTrue($authorizationChecker->isGranted($attribute));
    }

    public function createAccessDecisionManagerMock(bool $useVoteObject)
    {
        return $useVoteObject ?
            $this->getMockBuilder(AccessDecisionManagerInterface::class)
                ->onlyMethods(['decide'])
                ->addMethods(['getDecision'])
                ->getMock():
            $this->createMock(AccessDecisionManagerInterface::class);
    }
}
