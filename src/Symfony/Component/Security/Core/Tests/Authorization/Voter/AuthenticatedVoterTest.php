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
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class AuthenticatedVoterTest extends TestCase
{
    /**
     * @dataProvider getVoteTests
     */
    public function testVote($authenticated, $attributes, $expected)
    {
        $voter = new AuthenticatedVoter(new AuthenticationTrustResolver());

        $this->assertEquals($expected, $voter->vote($this->getToken($authenticated), null, $attributes));
    }

    public function getVoteTests()
    {
        return [
            ['fully', [], Vote::createAbstain()],
            ['fully', ['FOO'], Vote::createAbstain()],
            ['remembered', [], Vote::createAbstain()],
            ['remembered', ['FOO'], Vote::createAbstain()],
            ['anonymously', [], Vote::createAbstain()],
            ['anonymously', ['FOO'], Vote::createAbstain()],

            ['fully', ['IS_AUTHENTICATED_ANONYMOUSLY'], Vote::createGranted()],
            ['remembered', ['IS_AUTHENTICATED_ANONYMOUSLY'], Vote::createGranted()],
            ['anonymously', ['IS_AUTHENTICATED_ANONYMOUSLY'], Vote::createGranted()],

            ['fully', ['IS_AUTHENTICATED_REMEMBERED'], Vote::createGranted()],
            ['remembered', ['IS_AUTHENTICATED_REMEMBERED'], Vote::createGranted()],
            ['anonymously', ['IS_AUTHENTICATED_REMEMBERED'], Vote::createDenied()],

            ['fully', ['IS_AUTHENTICATED_FULLY'], Vote::createGranted()],
            ['remembered', ['IS_AUTHENTICATED_FULLY'], Vote::createDenied()],
            ['anonymously', ['IS_AUTHENTICATED_FULLY'], Vote::createDenied()],

            ['fully', ['IS_ANONYMOUS'], Vote::createDenied()],
            ['remembered', ['IS_ANONYMOUS'], Vote::createDenied()],
            ['anonymously', ['IS_ANONYMOUS'], Vote::createGranted()],

            ['fully', ['IS_IMPERSONATOR'], Vote::createDenied()],
            ['remembered', ['IS_IMPERSONATOR'], Vote::createDenied()],
            ['anonymously', ['IS_IMPERSONATOR'], Vote::createDenied()],
            ['impersonated', ['IS_IMPERSONATOR'], Vote::createGranted()],
        ];
    }

    protected function getToken($authenticated)
    {
        if ('fully' === $authenticated) {
            return $this->createMock(TokenInterface::class);
        } elseif ('remembered' === $authenticated) {
            return $this->getMockBuilder(RememberMeToken::class)->setMethods(['setPersistent'])->disableOriginalConstructor()->getMock();
        } elseif ('impersonated' === $authenticated) {
            return $this->getMockBuilder(SwitchUserToken::class)->disableOriginalConstructor()->getMock();
        } else {
            return $this->getMockBuilder(AnonymousToken::class)->setConstructorArgs(['', ''])->getMock();
        }
    }
}
