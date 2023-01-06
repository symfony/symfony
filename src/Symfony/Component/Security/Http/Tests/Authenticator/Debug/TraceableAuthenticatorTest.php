<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Debug\TraceableAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class TraceableAuthenticatorTest extends TestCase
{
    public function testGetInfo()
    {
        $request = new Request();
        $passport = new SelfValidatingPassport(new UserBadge('robin', function () {}));

        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $authenticator
            ->expects($this->once())
            ->method('authenticate')
            ->with($request)
            ->willReturn($passport);

        $traceable = new TraceableAuthenticator($authenticator);
        $this->assertSame($passport, $traceable->authenticate($request));
        $this->assertSame($passport, $traceable->getInfo()['passport']);
    }
}
