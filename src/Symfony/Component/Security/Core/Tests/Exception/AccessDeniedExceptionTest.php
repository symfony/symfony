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
            AccessDecision::createDenied([
                Vote::createDenied('foo'),
                Vote::createDenied('bar'),
                Vote::createDenied('baz'),
            ]),
            'Access Denied. Decision messages are "foo" and "bar" and "baz"',
        ];

        yield [
            AccessDecision::createDenied(),
            'Access Denied.',
        ];

        yield [
            AccessDecision::createDenied([
                Vote::createAbstain('foo'),
                Vote::createDenied('bar'),
                Vote::createAbstain('baz'),
            ]),
            'Access Denied. Decision message is "bar"',
        ];

        yield [
            AccessDecision::createGranted([
                Vote::createDenied('foo'),
            ]),
            'Access Denied. Decision message is "foo"',
        ];

        yield [
            AccessDecision::createGranted([
                Vote::createDenied(['foo', 'bar']),
                Vote::createDenied(['baz', 'qux']),
            ]),
            'Access Denied. Decision messages are "foo, bar" and "baz, qux"',
        ];
    }
}
