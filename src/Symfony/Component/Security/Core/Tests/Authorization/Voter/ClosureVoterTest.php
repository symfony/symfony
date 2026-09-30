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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\ClosureVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
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

    #[DataProvider('provideReAuthenticationRequests')]
    public function testADeniedClosureRequestsTheReAuthenticationOfTheCheckItFailedOn(string $role, ?string $requestedAttribute)
    {
        $token = new UsernamePasswordToken(new InMemoryUser('john', 'password', [$role]), 'main', [$role]);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken($token);
        $voter = new ClosureVoter(new AuthorizationChecker($tokenStorage, new AccessDecisionManager([new AuthenticatedVoter(new AuthenticationTrustResolver(300)), new RoleVoter()])));

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, [static fn (IsGrantedContext $context): bool => $context->isGranted('ROLE_ADMIN') && $context->isAuthenticatedRecently()], $vote = new Vote()));
        $this->assertSame($requestedAttribute, $vote->reAuthenticationAttribute);
    }

    public static function provideReAuthenticationRequests(): iterable
    {
        yield 'stale admin' => ['ROLE_ADMIN', AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY];
        yield 'not an admin' => ['ROLE_USER', null];
    }
}
