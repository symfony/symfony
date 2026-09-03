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

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Strategy\AccessDecisionStrategyInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AccessDecisionManagerTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    public function provideBadVoterResults(): array
    {
        return [
            [3],
            [true],
        ];
    }

    public function testVoterCalls()
    {
        $voters = [
            $this->getExpectedVoter(VoterInterface::ACCESS_DENIED),
            $this->getExpectedVoter(VoterInterface::ACCESS_GRANTED),
            $this->getUnexpectedVoter(),
        ];

        $strategy = new class implements AccessDecisionStrategyInterface {
            public function decide(\Traversable $results, ?AccessDecision $accessDecision = null): bool
            {
                $i = 0;
                foreach ($results as $result) {
                    switch ($i++) {
                        case 0:
                            Assert::assertSame(VoterInterface::ACCESS_DENIED, $result);

                            break;
                        case 1:
                            Assert::assertSame(VoterInterface::ACCESS_GRANTED, $result);

                            return true;
                    }
                }

                return false;
            }
        };

        $manager = new AccessDecisionManager($voters, $strategy);

        $this->assertTrue($manager->decide(new NullToken(), ['ROLE_FOO']));
    }

    public function testCacheableVoters()
    {
        $token = new NullToken();
        $voter = $this->createMock(CacheableVoterInterface::class);

        $voter
            ->expects($this->once())
            ->method('supportsAttribute')
            ->with('foo')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('vote')
            ->with($token, 'bar', ['foo'])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->decide($token, ['foo'], 'bar'));
    }

    public function testCacheableVotersIgnoresNonStringAttributes()
    {
        $token = new NullToken();
        $voter = $this->createMock(CacheableVoterInterface::class);
        $voter
            ->expects($this->never())
            ->method('supportsAttribute');
        $voter
            ->expects($this->once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('vote')
            ->with($token, 'bar', [1337])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->decide($token, [1337], 'bar'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testCacheableVotersWithMultipleAttributes()
    {
        $token = new NullToken();
        $voter = $this->createMock(CacheableVoterInterface::class);
        $voter
            ->expects($this->exactly(2))
            ->method('supportsAttribute')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    [['foo'], false],
                    [['bar'], true],
                ];

                [$expectedArgs, $return] = array_shift($series);
                $this->assertSame($expectedArgs, $args);

                return $return;
            });
        $voter
            ->expects($this->once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('vote')
            ->with($token, 'bar', ['foo', 'bar'])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->decide($token, ['foo', 'bar'], 'bar', true));
    }

    public function testCacheableVotersWithEmptyAttributes()
    {
        $token = new NullToken();
        $voter = $this->createMock(CacheableVoterInterface::class);
        $voter
            ->expects($this->never())
            ->method('supportsAttribute');
        $voter
            ->expects($this->once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('vote')
            ->with($token, 'bar', [])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->decide($token, [], 'bar'));
    }

    public function testCacheableVotersSupportsMethodsCalledOnce()
    {
        $token = new NullToken();
        $voter = $this->createMock(CacheableVoterInterface::class);
        $voter
            ->expects($this->once())
            ->method('supportsAttribute')
            ->with('foo')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects($this->exactly(2))
            ->method('vote')
            ->with($token, 'bar', ['foo'])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->decide($token, ['foo'], 'bar'));
        $this->assertTrue($manager->decide($token, ['foo'], 'bar'));
    }

    public function testCacheableVotersNotCalled()
    {
        $token = new NullToken();
        $voter = $this->createMock(CacheableVoterInterface::class);
        $voter
            ->expects($this->once())
            ->method('supportsAttribute')
            ->with('foo')
            ->willReturn(false);
        $voter
            ->expects($this->never())
            ->method('supportsType');
        $voter
            ->expects($this->never())
            ->method('vote');

        $manager = new AccessDecisionManager([$voter]);
        $this->assertFalse($manager->decide($token, ['foo'], 'bar'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testCacheableVotersWithMultipleAttributesAndNonString()
    {
        $token = new NullToken();
        $voter = $this->createMock(CacheableVoterInterface::class);
        $voter
            ->expects($this->once())
            ->method('supportsAttribute')
            ->with('foo')
            ->willReturn(false);
        $voter
            // Voter does not support "foo", but given 1337 is not a string, it implicitly supports it.
            ->expects($this->once())
            ->method('supportsType')
            ->with('string')
            ->willReturn(true);
        $voter
            ->expects($this->once())
            ->method('vote')
            ->with($token, 'bar', ['foo', 1337])
            ->willReturn(VoterInterface::ACCESS_GRANTED);

        $manager = new AccessDecisionManager([$voter]);
        $this->assertTrue($manager->decide($token, ['foo', 1337], 'bar', true));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testPassingMultipleAttributesIsDeprecated()
    {
        $manager = new AccessDecisionManager([$this->getExpectedVoter(VoterInterface::ACCESS_GRANTED)]);

        $this->expectUserDeprecationMessage('Since symfony/security-core 8.2: Passing more than one Security attribute to "Symfony\\Component\\Security\\Core\\Authorization\\AccessDecisionManager::decide()" is deprecated, pass a single attribute instead. For an "access_control" rule, move the roles to "allow_if" or to the role hierarchy. The "$allowMultipleAttributes" argument will be removed in 9.0.');

        $this->assertTrue($manager->decide(new NullToken(), ['ROLE_FOO', 'ROLE_BAR'], null, true));
    }

    public function testPassingASingleAttributeIsNotDeprecated()
    {
        $manager = new AccessDecisionManager([$this->getExpectedVoter(VoterInterface::ACCESS_GRANTED)]);

        $this->assertTrue($manager->decide(new NullToken(), ['ROLE_FOO'], null, true));
    }

    private function getExpectedVoter(int $vote): VoterInterface
    {
        $voter = $this->createMock(VoterInterface::class);
        $voter->expects($this->once())
            ->method('vote')
            ->willReturn($vote);

        return $voter;
    }

    private function getUnexpectedVoter(): VoterInterface
    {
        $voter = $this->createMock(VoterInterface::class);
        $voter->expects($this->never())->method('vote');

        return $voter;
    }
}
