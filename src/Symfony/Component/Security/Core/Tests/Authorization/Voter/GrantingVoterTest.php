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
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\GrantingVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class GrantingVoterTest extends TestCase
{
    public static function provideVotes(): iterable
    {
        yield 'granted if the attribute and the type are supported and the rule grants' => [['EDIT'], new GrantingVoterTest_Subject(), VoterInterface::ACCESS_GRANTED];
        yield 'granted for a subclass of a supported type' => [['EDIT'], new GrantingVoterTest_ChildSubject(), VoterInterface::ACCESS_GRANTED];
        yield 'granted if one of the attributes grants' => [['CREATE', 'EDIT'], new GrantingVoterTest_Subject(), VoterInterface::ACCESS_GRANTED];
        yield 'abstains if the rule does not grant' => [['CREATE'], new GrantingVoterTest_Subject(), VoterInterface::ACCESS_ABSTAIN];
        yield 'abstains if the attribute is not supported' => [['DELETE'], new GrantingVoterTest_Subject(), VoterInterface::ACCESS_ABSTAIN];
        yield 'abstains if the type is not supported' => [['EDIT'], new \stdClass(), VoterInterface::ACCESS_ABSTAIN];
        yield 'abstains if the subject is null' => [['EDIT'], null, VoterInterface::ACCESS_ABSTAIN];
        yield 'abstains if the attribute is not a string' => [[new \stdClass()], new GrantingVoterTest_Subject(), VoterInterface::ACCESS_ABSTAIN];
        yield 'abstains if no attributes were provided' => [[], new GrantingVoterTest_Subject(), VoterInterface::ACCESS_ABSTAIN];
    }

    #[DataProvider('provideVotes')]
    public function testVote(array $attributes, mixed $subject, int $expectedVote)
    {
        $this->assertSame($expectedVote, (new GrantingVoterTest_Voter())->vote(new NullToken(), $subject, $attributes));
    }

    public function testRuleExplainsTheVote()
    {
        $vote = new Vote();

        (new GrantingVoterTest_Voter())->vote(new NullToken(), new GrantingVoterTest_Subject(), ['EDIT'], $vote);

        $this->assertSame(['EDIT is granted.'], $vote->reasons);
    }

    public function testVotersSharingAnAttributeDoNotBlockEachOtherUnderTheUnanimousStrategy()
    {
        $accessDecisionManager = new AccessDecisionManager([
            new GrantingVoterTest_Voter(withheld: 'CREATE'),
            new GrantingVoterTest_Voter(withheld: 'EDIT'),
        ], new UnanimousStrategy());

        $accessDecision = new AccessDecision();

        $this->assertTrue($accessDecisionManager->decide(new NullToken(), ['EDIT'], new GrantingVoterTest_Subject(), $accessDecision));
        $this->assertSame([VoterInterface::ACCESS_GRANTED, VoterInterface::ACCESS_ABSTAIN], array_map(static fn (Vote $vote) => $vote->result, $accessDecision->votes));
        $this->assertTrue($accessDecisionManager->decide(new NullToken(), ['CREATE'], new GrantingVoterTest_Subject()));
    }

    public function testAccessIsDeniedWhenAllVotersAbstainUnderTheUnanimousStrategy()
    {
        $accessDecisionManager = new AccessDecisionManager([new GrantingVoterTest_Voter(withheld: 'EDIT')], new UnanimousStrategy());

        $this->assertFalse($accessDecisionManager->decide(new NullToken(), ['EDIT'], new GrantingVoterTest_Subject()));
    }
}

class GrantingVoterTest_Subject
{
}

class GrantingVoterTest_ChildSubject extends GrantingVoterTest_Subject
{
}

/**
 * @extends GrantingVoter<'EDIT'|'CREATE', GrantingVoterTest_Subject>
 */
class GrantingVoterTest_Voter extends GrantingVoter
{
    public function __construct(
        private string $withheld = 'CREATE',
    ) {
    }

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, ['EDIT', 'CREATE'], true);
    }

    public function supportsType(string $subjectType): bool
    {
        return is_a($subjectType, GrantingVoterTest_Subject::class, true);
    }

    protected function grants(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // grants anything but the withheld attribute, so that only the filters can make the voter abstain on unsupported input
        if ($this->withheld === $attribute) {
            return false;
        }

        $vote?->addReason(\sprintf('%s is granted.', $attribute));

        return true;
    }
}
