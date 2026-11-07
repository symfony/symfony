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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Strategy\AffirmativeStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\DenyingVoter;
use Symfony\Component\Security\Core\Authorization\Voter\GrantingVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class DenyingVoterTest extends TestCase
{
    public static function provideVotes(): iterable
    {
        yield 'denied if the attribute and the type are supported and the rule denies' => [['EDIT'], new DenyingVoterTest_Subject(), VoterInterface::ACCESS_DENIED];
        yield 'denied for a subclass of a supported type' => [['EDIT'], new DenyingVoterTest_ChildSubject(), VoterInterface::ACCESS_DENIED];
        yield 'denied if one of the attributes is denied' => [['DELETE', 'EDIT'], new DenyingVoterTest_Subject(), VoterInterface::ACCESS_DENIED];
        yield 'abstains if the rule does not deny' => [['DELETE'], new DenyingVoterTest_Subject(), VoterInterface::ACCESS_ABSTAIN];
        yield 'abstains if the attribute is not supported' => [['CREATE'], new DenyingVoterTest_Subject(), VoterInterface::ACCESS_ABSTAIN];
        yield 'abstains if the type is not supported' => [['EDIT'], new \stdClass(), VoterInterface::ACCESS_ABSTAIN];
        yield 'abstains if the subject is null' => [['EDIT'], null, VoterInterface::ACCESS_ABSTAIN];
        yield 'abstains if the attribute is not a string' => [[new \stdClass()], new DenyingVoterTest_Subject(), VoterInterface::ACCESS_ABSTAIN];
        yield 'abstains if no attributes were provided' => [[], new DenyingVoterTest_Subject(), VoterInterface::ACCESS_ABSTAIN];
    }

    #[DataProvider('provideVotes')]
    public function testVote(array $attributes, mixed $subject, int $expectedVote)
    {
        $this->assertSame($expectedVote, (new DenyingVoterTest_Voter())->vote(new NullToken(), $subject, $attributes));
    }

    public function testRuleExplainsTheDenial()
    {
        $vote = new Vote();

        (new DenyingVoterTest_Voter())->vote(new NullToken(), new DenyingVoterTest_Subject(), ['EDIT'], $vote);

        $this->assertSame(['EDIT is denied.'], $vote->reasons);
    }

    public function testDenialWinsOverAGrantUnderTheUnanimousStrategy()
    {
        $accessDecisionManager = new AccessDecisionManager([new DenyingVoterTest_GrantingVoter(), new DenyingVoterTest_Voter()], new UnanimousStrategy());

        $accessDecision = new AccessDecision();

        $this->assertFalse($accessDecisionManager->decide(new NullToken(), ['EDIT'], new DenyingVoterTest_Subject(), $accessDecision));
        $this->assertSame(['EDIT is denied.'], $accessDecision->votes[1]->reasons);
        $this->assertTrue($accessDecisionManager->decide(new NullToken(), ['DELETE'], new DenyingVoterTest_Subject()));
    }

    public function testDenialIsOverriddenByAGrantUnderTheAffirmativeStrategy()
    {
        $accessDecisionManager = new AccessDecisionManager([new DenyingVoterTest_GrantingVoter(), new DenyingVoterTest_Voter()], new AffirmativeStrategy());

        $this->assertTrue($accessDecisionManager->decide(new NullToken(), ['EDIT'], new DenyingVoterTest_Subject()));
    }
}

class DenyingVoterTest_Subject
{
}

class DenyingVoterTest_ChildSubject extends DenyingVoterTest_Subject
{
}

/**
 * @extends DenyingVoter<'EDIT'|'DELETE', DenyingVoterTest_Subject>
 */
class DenyingVoterTest_Voter extends DenyingVoter
{
    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, ['EDIT', 'DELETE'], true);
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, DenyingVoterTest_Subject::class, true);
    }

    protected function denies(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // denies anything but DELETE, so that only the filters can make the voter abstain on unsupported input
        if ('DELETE' === $attribute) {
            return false;
        }

        $vote?->addReason(\sprintf('%s is denied.', $attribute));

        return true;
    }
}

/**
 * @extends GrantingVoter<string, DenyingVoterTest_Subject>
 */
class DenyingVoterTest_GrantingVoter extends GrantingVoter
{
    public function supportsAttribute(string $attribute): bool
    {
        return true;
    }

    public function supportsType(string $subjectType): bool
    {
        return true;
    }

    protected function grants(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return true;
    }
}
