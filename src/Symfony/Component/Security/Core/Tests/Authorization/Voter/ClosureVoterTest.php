<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization\Voter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\ClosureVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGrantedContext;

/**
 * @requires function Symfony\Component\Security\Http\Attribute\IsGrantedContext::isGranted
 */
class ClosureVoterTest extends TestCase
{
    private ClosureVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ClosureVoter(
            $this->createMock(AuthorizationCheckerInterface::class),
        );
    }

    public function testEmptyAttributeAbstains()
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote(
            $this->createMock(TokenInterface::class),
            null,
            [])
        );
    }

    public function testClosureReturningFalseDeniesAccess()
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn([]);
        $token->method('getUser')->willReturn($this->createMock(UserInterface::class));

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote(
            $token,
            null,
            [fn () => false]
        ));
    }

    public function testClosureReturningTrueGrantsAccess()
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn([]);
        $token->method('getUser')->willReturn($this->createMock(UserInterface::class));

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote(
            $token,
            null,
            [fn () => true]
        ));
    }

    public function testArgumentsContent()
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn(['MY_ROLE', 'ANOTHER_ROLE']);
        $token->method('getUser')->willReturn($this->createMock(UserInterface::class));

        $outerSubject = new \stdClass();

        $this->voter->vote(
            $token,
            $outerSubject,
            [function (IsGrantedContext $context, \stdClass $subject) use ($outerSubject) {
                $this->assertSame($outerSubject, $subject);

                return true;
            }]
        );
    }
}
