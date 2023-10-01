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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class VoterTest extends TestCase
{
    use ExpectDeprecationTrait;

    protected TokenInterface $token;

    protected function setUp(): void
    {
        $this->token = $this->createMock(TokenInterface::class);
    }

    public static function getTests(): array
    {
        $voter = new VoterTest_Voter();
        $integerVoter = new IntegerVoterTest_Voter();

        return [
            [$voter, ['EDIT'], Vote::createGranted(), new \stdClass(), 'ACCESS_GRANTED if attribute and class are supported and attribute grants access'],
            [$voter, ['CREATE'], Vote::createDenied(), new \stdClass(), 'ACCESS_DENIED if attribute and class are supported and attribute does not grant access'],

            [$voter, ['DELETE', 'EDIT'], Vote::createGranted(), new \stdClass(), 'ACCESS_GRANTED if one attribute is supported and grants access'],
            [$voter, ['DELETE', 'CREATE'], Vote::createDenied(), new \stdClass(), 'ACCESS_DENIED if one attribute is supported and denies access'],

            [$voter, ['CREATE', 'EDIT'], Vote::createGranted(), new \stdClass(), 'ACCESS_GRANTED if one attribute grants access'],

            [$voter, ['DELETE'], Vote::createAbstain(), new \stdClass(), 'ACCESS_ABSTAIN if no attribute is supported'],

            [$voter, ['EDIT'], Vote::createAbstain(), new class() {}, 'ACCESS_ABSTAIN if class is not supported'],

            [$voter, ['EDIT'], Vote::createAbstain(), null, 'ACCESS_ABSTAIN if object is null'],

            [$voter, [], Vote::createAbstain(), new \stdClass(), 'ACCESS_ABSTAIN if no attributes were provided'],

            [$voter, [new StringableAttribute()], Vote::createGranted(), new \stdClass(), 'ACCESS_GRANTED if attribute and class are supported and attribute grants access'],

            [$voter, [new \stdClass()], Vote::createAbstain(), new \stdClass(), 'ACCESS_ABSTAIN if attributes were not strings'],

            [$integerVoter, [42], Vote::createGranted(), new \stdClass(), 'ACCESS_GRANTED if attribute is an integer'],
        ];
    }

    /**
     * @dataProvider getTests
     */
    public function testGetVote(VoterInterface $voter, array $attributes, $expectedVote, $object, $message)
    {
        $this->assertEquals($expectedVote, $voter->getVote($this->token, $object, $attributes), $message);
    }

    public function getTestsLegacy()
    {
        $voter = new VoterLegacyTest_Voter();
        $integerVoter = new IntegerVoterLegacyTest_Voter();

        return [
            [$voter, ['EDIT'], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute and class are supported and attribute grants access'],
            [$voter, ['CREATE'], VoterInterface::ACCESS_DENIED, new \stdClass(), 'ACCESS_DENIED if attribute and class are supported and attribute does not grant access'],

            [$voter, ['DELETE', 'EDIT'], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if one attribute is supported and grants access'],
            [$voter, ['DELETE', 'CREATE'], VoterInterface::ACCESS_DENIED, new \stdClass(), 'ACCESS_DENIED if one attribute is supported and denies access'],

            [$voter, ['CREATE', 'EDIT'], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if one attribute grants access'],

            [$voter, ['DELETE'], VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if no attribute is supported'],

            [$voter, ['EDIT'], VoterInterface::ACCESS_ABSTAIN, new class() {}, 'ACCESS_ABSTAIN if class is not supported'],

            [$voter, ['EDIT'], VoterInterface::ACCESS_ABSTAIN, null, 'ACCESS_ABSTAIN if object is null'],

            [$voter, [], VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if no attributes were provided'],

            [$voter, [new StringableAttribute()], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute and class are supported and attribute grants access'],

            [$voter, [new \stdClass()], VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if attributes were not strings'],

            [$integerVoter, [42], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute is an integer'],
        ];
    }

    /**
     * @group legacy
     * @dataProvider getTestsLegacy
     */
    public function testVoteLegacy(VoterInterface $voter, array $attributes, $expectedVote, $object, $message)
    {
        $this->expectDeprecation('Since symfony/security-core 6.3: Method "%s::vote()" has been deprecated, use "%s::getVote()" instead.');
        $this->assertEquals($expectedVote, $voter->vote($this->token, $object, $attributes), $message);
    }

    public function testVoteMessage()
    {
        $voter = new IntegerVoterVoteTest_Voter();
        $vote = $voter->getVote($this->token, new \stdClass(), [43]);
        $this->assertSame('foobar message', $vote->getMessage());
    }

    public function testVoteMessageMultipleAttributes()
    {
        $voter = new IntegerVoterVoteTest_Voter();
        $vote = $voter->getVote($this->token, new \stdClass(), [43, 44]);
        $this->assertSame('foobar message, foobar message', $vote->getMessage());
    }

    public function testGetVoteWithTypeError()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Should error');
        $voter = new TypeErrorVoterTest_Voter();
        $voter->getVote($this->token, new \stdClass(), ['EDIT']);
    }
}

class VoterTest_Voter extends Voter
{
    protected function voteOnAttribute(string $attribute, mixed $object, TokenInterface $token): Vote
    {
        return 'EDIT' === $attribute ? Vote::createGranted() : Vote::createDenied();
    }

    protected function supports(string $attribute, $object): bool
    {
        return $object instanceof \stdClass && \in_array($attribute, ['EDIT', 'CREATE']);
    }
}

class VoterLegacyTest_Voter extends Voter
{
    protected function voteOnAttribute(string $attribute, $object, TokenInterface $token): bool
    {
        return 'EDIT' === $attribute;
    }

    protected function supports(string $attribute, $object): bool
    {
        return $object instanceof \stdClass && \in_array($attribute, ['EDIT', 'CREATE']);
    }
}

class IntegerVoterTest_Voter extends Voter
{
    protected function voteOnAttribute($attribute, $object, TokenInterface $token): Vote
    {
        return 42 === $attribute ? Vote::createGranted() : Vote::createDenied();
    }

    protected function supports($attribute, $object): bool
    {
        return $object instanceof \stdClass && \is_int($attribute);
    }
}

class IntegerVoterLegacyTest_Voter extends Voter
{
    protected function voteOnAttribute($attribute, $object, TokenInterface $token): bool
    {
        return 42 === $attribute;
    }

    protected function supports($attribute, $object): bool
    {
        return $object instanceof \stdClass && \is_int($attribute);
    }
}

class IntegerVoterVoteTest_Voter extends Voter
{
    protected function voteOnAttribute($attribute, $object, TokenInterface $token): Vote
    {
        if (42 === $attribute) {
            return Vote::createGranted();
        }

        return Vote::createDenied('foobar message');
    }

    protected function supports($attribute, $object): bool
    {
        return $object instanceof \stdClass && \is_int($attribute);
    }
}

class TypeErrorVoterTest_Voter extends Voter
{
    protected function voteOnAttribute($attribute, $object, TokenInterface $token): bool
    {
        return false;
    }

    protected function supports($attribute, $object): bool
    {
        throw new \TypeError('Should error');
    }
}

class StringableAttribute
{
    public function __toString(): string
    {
        return 'EDIT';
    }
}
