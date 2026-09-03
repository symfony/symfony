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
use Symfony\Component\Security\Http\Authenticator\UnsupportedReasonProviderInterface;

class TraceableAuthenticatorTest extends TestCase
{
    public function testUnsupportedReasonIsCollected()
    {
        $request = new Request();

        $authenticator = $this->createMock(ExplainingAuthenticator::class);
        $authenticator->method('supports')->willReturn(false);
        $authenticator->expects($this->once())
            ->method('getUnsupportedReason')
            ->with($request)
            ->willReturn('the moon is not full');

        $traceable = new TraceableAuthenticator($authenticator);

        $this->assertFalse($traceable->supports($request));
        $this->assertSame('the moon is not full', $traceable->getInfo()['unsupportedReason']);
    }

    public function testUnsupportedReasonIsNullWhenTheAuthenticatorCannotExplain()
    {
        $authenticator = $this->createStub(AuthenticatorInterface::class);
        $authenticator->method('supports')->willReturn(false);

        $traceable = new TraceableAuthenticator($authenticator);

        $this->assertFalse($traceable->supports(new Request()));
        $this->assertArrayHasKey('unsupportedReason', $traceable->getInfo());
        $this->assertNull($traceable->getInfo()['unsupportedReason']);
    }

    public function testUnsupportedReasonIsNotAskedForWhenTheRequestIsSupported()
    {
        $authenticator = $this->createMock(ExplainingAuthenticator::class);
        $authenticator->method('supports')->willReturn(true);
        $authenticator->expects($this->never())->method('getUnsupportedReason');

        $traceable = new TraceableAuthenticator($authenticator);

        $this->assertTrue($traceable->supports(new Request()));
        $this->assertArrayHasKey('unsupportedReason', $traceable->getInfo());
        $this->assertNull($traceable->getInfo()['unsupportedReason']);
    }

    public function testUnsupportedReasonIsForwardedByTheDecorator()
    {
        $request = new Request();

        $authenticator = $this->createMock(ExplainingAuthenticator::class);
        $authenticator->expects($this->once())
            ->method('getUnsupportedReason')
            ->with($request)
            ->willReturn('the moon is not full');

        $traceable = new TraceableAuthenticator($authenticator);

        $this->assertInstanceOf(UnsupportedReasonProviderInterface::class, $traceable);
        $this->assertSame('the moon is not full', $traceable->getUnsupportedReason($request));
    }

    public function testUnsupportedReasonIsNullWhenTheDecoratedAuthenticatorCannotExplain()
    {
        $traceable = new TraceableAuthenticator($this->createStub(AuthenticatorInterface::class));

        $this->assertNull($traceable->getUnsupportedReason(new Request()));
    }

    public function testGetInfo()
    {
        $request = new Request();
        $passport = new SelfValidatingPassport(new UserBadge('robin', static function () {}));

        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $authenticator->expects($this->once())
            ->method('supports')
            ->with($request)
            ->willReturn(true);

        $authenticator
            ->expects($this->once())
            ->method('authenticate')
            ->with($request)
            ->willReturn($passport);

        $traceable = new TraceableAuthenticator($authenticator);
        $this->assertTrue($traceable->supports($request));
        $this->assertSame($passport, $traceable->authenticate($request));
        $this->assertSame($passport, $traceable->getInfo()['passport']);
    }

    public function testGetInfoWithoutAuth()
    {
        $request = new Request();

        $authenticator = $this->createMock(AuthenticatorInterface::class);
        $authenticator->expects($this->once())
            ->method('supports')
            ->with($request)
            ->willReturn(false);

        $traceable = new TraceableAuthenticator($authenticator);
        $this->assertFalse($traceable->supports($request));
        $this->assertNull($traceable->getInfo()['passport']);
        $this->assertIsArray($traceable->getInfo()['badges']);
        $this->assertSame([], $traceable->getInfo()['badges']);
    }
}

interface ExplainingAuthenticator extends AuthenticatorInterface, UnsupportedReasonProviderInterface
{
}
