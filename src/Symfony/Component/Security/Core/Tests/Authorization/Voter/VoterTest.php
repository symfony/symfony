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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class VoterTest extends TestCase
{
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
            [$voter, ['EDIT'], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute and class are supported and attribute grants access'],
            [$voter, ['EDIT'], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute and class are supported and attribute grants access', new Vote()],
            [$voter, ['CREATE'], VoterInterface::ACCESS_DENIED, new \stdClass(), 'ACCESS_DENIED if attribute and class are supported and attribute does not grant access'],
            [$voter, ['CREATE'], VoterInterface::ACCESS_DENIED, new \stdClass(), 'ACCESS_DENIED if attribute and class are supported and attribute does not grant access', new Vote()],

            [$voter, ['DELETE', 'EDIT'], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if one attribute is supported and grants access'],
            [$voter, ['DELETE', 'EDIT'], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if one attribute is supported and grants access', new Vote()],
            [$voter, ['DELETE', 'CREATE'], VoterInterface::ACCESS_DENIED, new \stdClass(), 'ACCESS_DENIED if one attribute is supported and denies access'],
            [$voter, ['DELETE', 'CREATE'], VoterInterface::ACCESS_DENIED, new \stdClass(), 'ACCESS_DENIED if one attribute is supported and denies access', new Vote()],

            [$voter, ['CREATE', 'EDIT'], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if one attribute grants access'],
            [$voter, ['CREATE', 'EDIT'], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if one attribute grants access', new Vote()],

            [$voter, ['DELETE'], VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if no attribute is supported'],
            [$voter, ['DELETE'], VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if no attribute is supported', new Vote()],

            [$voter, ['EDIT'], VoterInterface::ACCESS_ABSTAIN, new class {}, 'ACCESS_ABSTAIN if class is not supported'],
            [$voter, ['EDIT'], VoterInterface::ACCESS_ABSTAIN, new class {}, 'ACCESS_ABSTAIN if class is not supported', new Vote()],

            [$voter, ['EDIT'], VoterInterface::ACCESS_ABSTAIN, null, 'ACCESS_ABSTAIN if object is null'],
            [$voter, ['EDIT'], VoterInterface::ACCESS_ABSTAIN, null, 'ACCESS_ABSTAIN if object is null', new Vote()],

            [$voter, [], VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if no attributes were provided'],
            [$voter, [], VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if no attributes were provided', new Vote()],

            [$voter, [new StringableAttribute()], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute and class are supported and attribute grants access'],
            [$voter, [new StringableAttribute()], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute and class are supported and attribute grants access', new Vote()],

            [$voter, [new \stdClass()], VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if attributes were not strings'],
            [$voter, [new \stdClass()], VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if attributes were not strings', new Vote()],

            [$integerVoter, [42], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute is an integer'],
            [$integerVoter, [42], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute is an integer', new Vote()],
        ];
    }

    #[DataProvider('getTests')]
    public function testVote(VoterInterface $voter, array $attributes, $expectedVote, $object, $message, ?Vote $vote = null)
    {
        $this->assertSame($expectedVote, $voter->vote($this->token, $object, $attributes, $vote), $message);

        if (null !== $vote) {
            self::assertSame($expectedVote, $vote->result);
        }
    }

    public function testVoteWithTypeError()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Should error');
        (new TypeErrorVoterTest_Voter())->vote($this->token, new \stdClass(), ['EDIT']);
    }
}

class VoterTest_Voter extends Voter
{
    protected function voteOnAttribute(string $attribute, $object, TokenInterface $token, ?Vote $vote = null): bool
    {
        return 'EDIT' === $attribute;
    }

    protected function supports(string $attribute, $object): bool
    {
        return $object instanceof \stdClass && \in_array($attribute, ['EDIT', 'CREATE'], true);
    }
}

class IntegerVoterTest_Voter extends Voter
{
    protected function voteOnAttribute($attribute, $object, TokenInterface $token, ?Vote $vote = null): bool
    {
        return 42 === $attribute;
    }

    protected function supports($attribute, $object): bool
    {
        return $object instanceof \stdClass && \is_int($attribute);
    }
}

class TypeErrorVoterTest_Voter extends Voter
{
    protected function voteOnAttribute($attribute, $object, TokenInterface $token, ?Vote $vote = null): bool
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
