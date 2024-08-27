<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AccessDeniedExceptionTest extends TestCase
{
    /**
     * @dataProvider getAccessDescisions
     */
    public function testSetAccessDecision(AccessDecision $accessDecision, string $expected)
    {
        $exception = new AccessDeniedException();
        $exception->setAccessDecision($accessDecision);

        $this->assertSame($expected, $exception->getMessage());
    }

    public function getAccessDescisions(): \Generator
    {
        yield [
            new AccessDecision(VoterInterface::ACCESS_DENIED, [
                new Vote(VoterInterface::ACCESS_DENIED, 'foo'),
                new Vote(VoterInterface::ACCESS_DENIED, 'bar'),
                new Vote(VoterInterface::ACCESS_DENIED, 'baz'),
            ]),
            'Access Denied.'.PHP_EOL.'Decision messages are "foo" and "bar" and "baz"',
        ];

        yield [
            new AccessDecision(VoterInterface::ACCESS_DENIED,),
            'Access Denied.',
        ];

        yield [
            new AccessDecision(VoterInterface::ACCESS_DENIED,[
                new Vote(VoterInterface::ACCESS_ABSTAIN,'foo'),
                new Vote(VoterInterface::ACCESS_DENIED,'bar'),
                new Vote(VoterInterface::ACCESS_ABSTAIN,'baz'),
            ]),
            'Access Denied.'.PHP_EOL.'Decision message is "bar"',
        ];

        yield [
            new AccessDecision(VoterInterface::ACCESS_GRANTED,[
                new Vote(VoterInterface::ACCESS_DENIED,'foo'),
            ]),
            'Access Denied.'.PHP_EOL.'Decision message is "foo"',
        ];

        yield [
            new AccessDecision(VoterInterface::ACCESS_GRANTED,[
                new Vote(VoterInterface::ACCESS_DENIED,['foo', 'bar']),
                new Vote(VoterInterface::ACCESS_DENIED,['baz', 'qux']),
            ]),
            'Access Denied.'.PHP_EOL.'Decision messages are "foo, bar" and "baz, qux"',
        ];
    }
}