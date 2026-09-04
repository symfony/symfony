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
use Symfony\Component\Security\Http\Authenticator\Debug\UnsupportedReasons;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class TraceableAuthenticatorTest extends TestCase
{
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

    public function testUnsupportedReasonsAreCollected()
    {
        $request = new Request();

        $authenticator = $this->createStub(AuthenticatorInterface::class);
        $authenticator->method('supports')->willReturnCallback(static function (Request $request): bool {
            $request->attributes->get(SecurityRequestAttributes::UNSUPPORTED_REASONS)->add('the moon is not full');

            return false;
        });

        $traceable = new TraceableAuthenticator($authenticator);
        $this->assertFalse($traceable->supports($request));
        $this->assertSame(['the moon is not full'], $traceable->getInfo()['unsupportedReasons']);
        $this->assertFalse($request->attributes->has(SecurityRequestAttributes::UNSUPPORTED_REASONS));
    }

    public function testUnsupportedReasonsAreEmptyWhenTheAuthenticatorGivesNone()
    {
        $request = new Request();

        $authenticator = $this->createStub(AuthenticatorInterface::class);
        $authenticator->method('supports')->willReturn(false);

        $traceable = new TraceableAuthenticator($authenticator);
        $this->assertSame([], $traceable->getInfo()['unsupportedReasons']);

        $this->assertFalse($traceable->supports($request));
        $this->assertSame([], $traceable->getInfo()['unsupportedReasons']);
        $this->assertFalse($request->attributes->has(SecurityRequestAttributes::UNSUPPORTED_REASONS));
    }

    public function testUnsupportedReasonsAreResetOnEachCall()
    {
        $request = new Request();
        $calls = 0;

        $authenticator = $this->createStub(AuthenticatorInterface::class);
        $authenticator->method('supports')->willReturnCallback(static function (Request $request) use (&$calls): bool {
            if (1 === ++$calls) {
                $request->attributes->get(SecurityRequestAttributes::UNSUPPORTED_REASONS)->add('the moon is not full');

                return false;
            }

            return true;
        });

        $traceable = new TraceableAuthenticator($authenticator);
        $traceable->supports($request);
        $this->assertSame(['the moon is not full'], $traceable->getInfo()['unsupportedReasons']);

        $this->assertTrue($traceable->supports($request));
        $this->assertSame([], $traceable->getInfo()['unsupportedReasons']);
    }

    public function testTheCollectorIsRemovedFromTheRequestWhenSupportsThrows()
    {
        $request = new Request();

        $authenticator = $this->createStub(AuthenticatorInterface::class);
        $authenticator->method('supports')->willThrowException(new \RuntimeException('boom'));

        $traceable = new TraceableAuthenticator($authenticator);

        try {
            $traceable->supports($request);
            $this->fail('An exception should have been thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertFalse($request->attributes->has(SecurityRequestAttributes::UNSUPPORTED_REASONS));
    }

    public function testTheCollectorIsOnlyPresentDuringSupports()
    {
        $request = new Request();
        $seen = null;

        $authenticator = $this->createStub(AuthenticatorInterface::class);
        $authenticator->method('supports')->willReturnCallback(static function (Request $request) use (&$seen): bool {
            $seen = $request->attributes->get(SecurityRequestAttributes::UNSUPPORTED_REASONS);

            return true;
        });

        $this->assertFalse($request->attributes->has(SecurityRequestAttributes::UNSUPPORTED_REASONS));
        (new TraceableAuthenticator($authenticator))->supports($request);
        $this->assertInstanceOf(UnsupportedReasons::class, $seen);
        $this->assertFalse($request->attributes->has(SecurityRequestAttributes::UNSUPPORTED_REASONS));
    }
}
