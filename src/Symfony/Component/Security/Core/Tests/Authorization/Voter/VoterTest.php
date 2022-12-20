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
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class VoterTest extends TestCase
{
    protected $token;

    protected function setUp(): void
    {
        $this->token = self::createMock(TokenInterface::class);
    }

    public function getTests()
    {
        $voter = new VoterTest_Voter();
        $integerVoter = new IntegerVoterTest_Voter();

        return [
            [$voter, ['EDIT'], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute and class are supported and attribute grants access'],
            [$voter, ['CREATE'], VoterInterface::ACCESS_DENIED, new \stdClass(), 'ACCESS_DENIED if attribute and class are supported and attribute does not grant access'],

            [$voter, ['DELETE', 'EDIT'], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if one attribute is supported and grants access'],
            [$voter, ['DELETE', 'CREATE'], VoterInterface::ACCESS_DENIED, new \stdClass(), 'ACCESS_DENIED if one attribute is supported and denies access'],

            [$voter, ['CREATE', 'EDIT'], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if one attribute grants access'],

            [$voter, ['DELETE'], VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if no attribute is supported'],

            [$voter, ['EDIT'], VoterInterface::ACCESS_ABSTAIN, $this, 'ACCESS_ABSTAIN if class is not supported'],

            [$voter, ['EDIT'], VoterInterface::ACCESS_ABSTAIN, null, 'ACCESS_ABSTAIN if object is null'],

            [$voter, [], VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if no attributes were provided'],

            [$voter, [new StringableAttribute()], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute and class are supported and attribute grants access'],

            [$voter, [new \stdClass()], VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if attributes were not strings'],

            [$integerVoter, [42], VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute is an integer'],
        ];
    }

    /**
     * @dataProvider getTests
     */
    public function testVote(VoterInterface $voter, array $attributes, $expectedVote, $object, $message)
    {
        self::assertEquals($expectedVote, $voter->vote($this->token, $object, $attributes), $message);
    }

    public function testVoteWithTypeError()
    {
        self::expectException(\TypeError::class);
        self::expectExceptionMessage('Should error');
        $voter = new TypeErrorVoterTest_Voter();
        $voter->vote($this->token, new \stdClass(), ['EDIT']);
    }
}

class VoterTest_Voter extends Voter
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
    protected function voteOnAttribute($attribute, $object, TokenInterface $token): bool
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
