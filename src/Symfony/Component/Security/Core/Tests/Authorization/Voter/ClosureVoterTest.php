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

use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\ClosureVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Attribute\IsGrantedContext;

#[RequiresMethod(IsGrantedContext::class, 'isGranted')]
class ClosureVoterTest extends TestCase
{
    private ClosureVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new ClosureVoter(
            $this->createStub(AuthorizationCheckerInterface::class),
        );
    }

    public function testEmptyAttributeAbstains()
    {
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote(
            new NullToken(),
            null,
            [])
        );
    }

    public function testClosureReturningFalseDeniesAccess()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('john', 'password'), 'main', []);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote(
            $token,
            null,
            [static fn () => false]
        ));
    }

    public function testClosureReturningTrueGrantsAccess()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('john', 'password'), 'main', []);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote(
            $token,
            null,
            [static fn () => true]
        ));
    }

    public function testArgumentsContent()
    {
        $token = new UsernamePasswordToken(new InMemoryUser('john', 'password'), 'main', ['MY_ROLE', 'ANOTHER_ROLE']);

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
