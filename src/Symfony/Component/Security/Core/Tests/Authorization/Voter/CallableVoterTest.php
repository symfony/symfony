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
use Symfony\Component\Security\Core\Authorization\Voter\CallableVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class CallableVoterTest extends TestCase
{
    public static function provideResults(): iterable
    {
        yield 'true grants' => [true, VoterInterface::ACCESS_GRANTED];
        yield 'false denies' => [false, VoterInterface::ACCESS_DENIED];
        yield 'null abstains' => [null, VoterInterface::ACCESS_ABSTAIN];
        yield 'ACCESS_GRANTED grants' => [VoterInterface::ACCESS_GRANTED, VoterInterface::ACCESS_GRANTED];
        yield 'ACCESS_DENIED denies' => [VoterInterface::ACCESS_DENIED, VoterInterface::ACCESS_DENIED];
        yield 'ACCESS_ABSTAIN abstains' => [VoterInterface::ACCESS_ABSTAIN, VoterInterface::ACCESS_ABSTAIN];
    }

    #[DataProvider('provideResults')]
    public function testVoteFollowsWhatTheCallableReturns(bool|int|null $returned, int $expected)
    {
        $voter = new CallableVoter(static fn () => $returned, [], ['EDIT'], null, 'rule');

        $this->assertSame($expected, $voter->vote(new NullToken(), null, ['EDIT']));
    }

    public function testVoteThrowsOnAnotherResult()
    {
        $voter = new CallableVoter(static fn () => 'yes', [], ['EDIT'], null, 'App\PostRules::canEdit');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('"App\PostRules::canEdit()" must return a boolean, null or one of the "%s::ACCESS_*" constants, "string" returned.', VoterInterface::class));

        $voter->vote(new NullToken(), null, ['EDIT']);
    }

    public function testVoteGrantsWhenOneAttributeIsGrantedAndDeniesWhenOneIsDenied()
    {
        $voter = new CallableVoter(static fn (string $attribute) => ['VIEW' => true, 'EDIT' => false, 'DELETE' => null][$attribute], ['attribute'], ['VIEW', 'EDIT', 'DELETE'], null, 'rule');

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(new NullToken(), null, ['EDIT', 'VIEW']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), null, ['DELETE', 'EDIT']));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), null, ['DELETE']));
    }

    public function testVoteAbstainsWithoutCallingOnWhatItDoesNotSupport()
    {
        $voter = new CallableVoter(static fn () => throw new \LogicException('Should not be called.'), [], ['EDIT'], [CallableVoterTest_Subject::class], 'rule');

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), new CallableVoterTest_Subject(), ['DELETE']));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), new \stdClass(), ['EDIT']));
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), new CallableVoterTest_Subject(), [new \stdClass()]));
    }

    public static function provideSubjectTypes(): iterable
    {
        yield 'all types when none is given' => [null, 'string', true];
        yield 'the given class' => [[CallableVoterTest_Subject::class], CallableVoterTest_Subject::class, true];
        yield 'a subclass of the given class' => [[CallableVoterTest_Subject::class], CallableVoterTest_ChildSubject::class, true];
        yield 'not another class' => [[CallableVoterTest_Subject::class], \stdClass::class, false];
        yield 'a builtin type' => [['int'], 'int', true];
        yield 'not another builtin type' => [['int'], 'string', false];
        yield 'null when given' => [[CallableVoterTest_Subject::class, 'null'], 'null', true];
        yield 'not null otherwise' => [[CallableVoterTest_Subject::class], 'null', false];
        yield 'any class for object' => [['object'], \stdClass::class, true];
        yield 'no builtin type for object' => [['object'], 'array', false];
    }

    #[DataProvider('provideSubjectTypes')]
    public function testSupportsType(?array $subjectTypes, string $subjectType, bool $expected)
    {
        $voter = new CallableVoter(static fn () => true, [], ['EDIT'], $subjectTypes, 'rule');

        $this->assertSame($expected, $voter->supportsType($subjectType));
    }

    public function testSupportsAttribute()
    {
        $voter = new CallableVoter(static fn () => true, [], ['VIEW', 'EDIT'], null, 'rule');

        $this->assertTrue($voter->supportsAttribute('EDIT'));
        $this->assertFalse($voter->supportsAttribute('DELETE'));
    }

    public function testCallableReceivesWhatItsParametersAskFor()
    {
        $subject = new CallableVoterTest_Subject();
        $token = new NullToken();
        $vote = new Vote();
        $arguments = null;

        $voter = new CallableVoter(static function (...$args) use (&$arguments) {
            $arguments = $args;

            return true;
        }, ['vote', 'attribute', 'subject', 'token'], ['EDIT'], null, 'rule');

        $voter->vote($token, $subject, ['EDIT'], $vote);

        $this->assertSame([$vote, 'EDIT', $subject, $token], $arguments);
    }

    public function testCallableExplainsItsVoteOnTheVote()
    {
        $voter = new CallableVoter(static function (?Vote $vote) {
            $vote?->addReason('A published post cannot be edited.');

            return false;
        }, ['vote'], ['EDIT'], null, 'rule');

        $vote = new Vote();

        $this->assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), null, ['EDIT'], $vote));
        $this->assertSame(['A published post cannot be edited.'], $vote->reasons);
    }

    public function testVoterIsNamedAfterTheCallable()
    {
        $this->assertSame('App\PostRules::canEdit', (string) new CallableVoter(static fn () => true, [], ['EDIT'], null, 'App\PostRules::canEdit'));
    }
}

class CallableVoterTest_Subject
{
}

class CallableVoterTest_ChildSubject extends CallableVoterTest_Subject
{
}
