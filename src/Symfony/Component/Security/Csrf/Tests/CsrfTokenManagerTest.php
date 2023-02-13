<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class CsrfTokenManagerTest extends TestCase
{
    public function testGetNonExistingTokenEmptyNamespace()
    {
        $this->assertGetNonExistingToken(...$this->getEmptyNamespaceMocks());
    }

    public function testGetNonExistingTokenHttpsNamespace()
    {
        $this->assertGetNonExistingToken(...$this->getHttpsNamespaceMocks());
    }

    public function testGetNonExistingTokenCustomNamespace()
    {
        $this->assertGetNonExistingToken(...$this->getCustomNamespaceMocks());
    }

    public function testGetNonExistingTokenRequestStack()
    {
        $this->assertGetNonExistingToken(...$this->getRequestStackMocks());
    }

    public function testGetNonExistingTokenClosure()
    {
        $this->assertGetNonExistingToken(...$this->getClosureMocks());
    }

    public function testGetNonExistingTokenRequestStackEmptyNamespace()
    {
        $this->assertGetNonExistingToken(...$this->getRequestStackWithEmptyNamespaceMocks());
    }

    private function assertGetNonExistingToken($namespace, $manager, $storage, $generator): void
    {
        $storage->expects($this->once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(false);

        $generator->expects($this->once())
            ->method('generateToken')
            ->willReturn('TOKEN');

        $storage->expects($this->once())
            ->method('setToken')
            ->with($namespace.'token_id', 'TOKEN');

        $token = $manager->getToken('token_id');

        $this->assertInstanceOf(CsrfToken::class, $token);
        $this->assertSame('token_id', $token->getId());
        $this->assertNotSame('TOKEN', $token->getValue());
    }

    public function testUseExistingTokenIfAvailableEmptyNamespace()
    {
        $this->assertUseExistingTokenIfAvailable(...$this->getEmptyNamespaceMocks());
    }

    public function testUseExistingTokenIfAvailableHttpsNamespace()
    {
        $this->assertUseExistingTokenIfAvailable(...$this->getHttpsNamespaceMocks());
    }

    public function testUseExistingTokenIfAvailableCustomNamespace()
    {
        $this->assertUseExistingTokenIfAvailable(...$this->getCustomNamespaceMocks());
    }

    public function testUseExistingTokenIfAvailableRequestStack()
    {
        $this->assertUseExistingTokenIfAvailable(...$this->getRequestStackMocks());
    }

    public function testUseExistingTokenIfAvailableClosure()
    {
        $this->assertUseExistingTokenIfAvailable(...$this->getClosureMocks());
    }

    public function testUseExistingTokenIfAvailableRequestStackEmptyNamespace()
    {
        $this->assertUseExistingTokenIfAvailable(...$this->getRequestStackWithEmptyNamespaceMocks());
    }

    private function assertUseExistingTokenIfAvailable($namespace, $manager, $storage): void
    {
        $storage->expects($this->once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage->expects($this->once())
            ->method('getToken')
            ->with($namespace.'token_id')
            ->willReturn('TOKEN');

        $token = $manager->getToken('token_id');

        $this->assertInstanceOf(CsrfToken::class, $token);
        $this->assertSame('token_id', $token->getId());
        $this->assertNotSame('TOKEN', $token->getValue());
    }

    public function testRandomizeTheTokenEmptyNamespace()
    {
        $this->assertRandomizeTheToken(...$this->getEmptyNamespaceMocks());
    }

    public function testRandomizeTheTokenHttpsNamespace()
    {
        $this->assertRandomizeTheToken(...$this->getHttpsNamespaceMocks());
    }

    public function testRandomizeTheTokenCustomNamespace()
    {
        $this->assertRandomizeTheToken(...$this->getCustomNamespaceMocks());
    }

    public function testRandomizeTheTokenRequestStack()
    {
        $this->assertRandomizeTheToken(...$this->getRequestStackMocks());
    }

    public function testRandomizeTheTokenClosure()
    {
        $this->assertRandomizeTheToken(...$this->getClosureMocks());
    }

    public function testRandomizeTheTokenRequestStackEmptyNamespace()
    {
        $this->assertRandomizeTheToken(...$this->getRequestStackWithEmptyNamespaceMocks());
    }

    private function assertRandomizeTheToken($namespace, $manager, $storage): void
    {
        $storage->expects($this->any())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage->expects($this->any())
            ->method('getToken')
            ->with($namespace.'token_id')
            ->willReturn('TOKEN');

        $values = [];
        $lengths = [];
        for ($i = 0; $i < 10; ++$i) {
            $token = $manager->getToken('token_id');
            $values[] = $token->getValue();
            $lengths[] = \strlen($token->getValue());
        }

        $this->assertCount(10, array_unique($values));
        $this->assertGreaterThan(2, \count(array_unique($lengths)));
    }

    public function testRefreshTokenAlwaysReturnsNewTokenEmptyNamespace()
    {
        $this->assertRefreshTokenAlwaysReturnsNewToken(...$this->getEmptyNamespaceMocks());
    }

    public function testRefreshTokenAlwaysReturnsNewTokenHttpsNamespace()
    {
        $this->assertRefreshTokenAlwaysReturnsNewToken(...$this->getHttpsNamespaceMocks());
    }

    public function testRefreshTokenAlwaysReturnsNewTokenCustomNamespace()
    {
        $this->assertRefreshTokenAlwaysReturnsNewToken(...$this->getCustomNamespaceMocks());
    }

    public function testRefreshTokenAlwaysReturnsNewTokenRequestStack()
    {
        $this->assertRefreshTokenAlwaysReturnsNewToken(...$this->getRequestStackMocks());
    }

    public function testRefreshTokenAlwaysReturnsNewTokenClosure()
    {
        $this->assertRefreshTokenAlwaysReturnsNewToken(...$this->getClosureMocks());
    }

    public function testRefreshTokenAlwaysReturnsNewTokenRequestStackEmptyNamespace()
    {
        $this->assertRefreshTokenAlwaysReturnsNewToken(...$this->getRequestStackWithEmptyNamespaceMocks());
    }

    private function assertRefreshTokenAlwaysReturnsNewToken($namespace, $manager, $storage, $generator): void
    {
        $storage->expects($this->never())
            ->method('hasToken');

        $generator->expects($this->once())
            ->method('generateToken')
            ->willReturn('TOKEN');

        $storage->expects($this->once())
            ->method('setToken')
            ->with($namespace.'token_id', 'TOKEN');

        $token = $manager->refreshToken('token_id');

        $this->assertInstanceOf(CsrfToken::class, $token);
        $this->assertSame('token_id', $token->getId());
        $this->assertNotSame('TOKEN', $token->getValue());
    }

    public function testMatchingTokenIsValidEmptyNamespace()
    {
        $this->assertMatchingTokenIsValid(...$this->getEmptyNamespaceMocks());
    }

    public function testMatchingTokenIsValidHttpsNamespace()
    {
        $this->assertMatchingTokenIsValid(...$this->getHttpsNamespaceMocks());
    }

    public function testMatchingTokenIsValidCustomNamespace()
    {
        $this->assertMatchingTokenIsValid(...$this->getCustomNamespaceMocks());
    }

    public function testMatchingTokenIsValidRequestStack()
    {
        $this->assertMatchingTokenIsValid(...$this->getRequestStackMocks());
    }

    public function testMatchingTokenIsValidClosure()
    {
        $this->assertMatchingTokenIsValid(...$this->getClosureMocks());
    }

    public function testMatchingTokenIsValidRequestStackEmptyNamespace()
    {
        $this->assertMatchingTokenIsValid(...$this->getRequestStackWithEmptyNamespaceMocks());
    }

    private function assertMatchingTokenIsValid($namespace, $manager, $storage)
    {
        $storage->expects($this->exactly(2))
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage->expects($this->exactly(2))
            ->method('getToken')
            ->with($namespace.'token_id')
            ->willReturn('TOKEN');

        $token = $manager->getToken('token_id');
        $this->assertNotSame('TOKEN', $token->getValue());
        $this->assertTrue($manager->isTokenValid($token));
    }

    public function testMatchingTokenIsValidWithLegacyTokenEmptyNamespace()
    {
        $this->assertMatchingTokenIsValidWithLegacyToken(...$this->getEmptyNamespaceMocks());
    }

    public function testMatchingTokenIsValidWithLegacyTokenHttpsNamespace()
    {
        $this->assertMatchingTokenIsValidWithLegacyToken(...$this->getHttpsNamespaceMocks());
    }

    public function testMatchingTokenIsValidWithLegacyTokenCustomNamespace()
    {
        $this->assertMatchingTokenIsValidWithLegacyToken(...$this->getCustomNamespaceMocks());
    }

    public function testMatchingTokenIsValidWithLegacyTokenRequestStack()
    {
        $this->assertMatchingTokenIsValidWithLegacyToken(...$this->getRequestStackMocks());
    }

    public function testMatchingTokenIsValidWithLegacyTokenClosure()
    {
        $this->assertMatchingTokenIsValidWithLegacyToken(...$this->getClosureMocks());
    }

    public function testMatchingTokenIsValidWithLegacyTokenRequestStackEmptyNamespace()
    {
        $this->assertMatchingTokenIsValidWithLegacyToken(...$this->getRequestStackWithEmptyNamespaceMocks());
    }

    private function assertMatchingTokenIsValidWithLegacyToken($namespace, $manager, $storage): void
    {
        $storage->expects($this->once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage->expects($this->once())
            ->method('getToken')
            ->with($namespace.'token_id')
            ->willReturn('TOKEN');

        $this->assertTrue($manager->isTokenValid(new CsrfToken('token_id', 'TOKEN')));
    }

    public function testNonMatchingTokenIsNotValidEmptyNamespace()
    {
        $this->assertNonMatchingTokenIsNotValid(...$this->getEmptyNamespaceMocks());
    }

    public function testNonMatchingTokenIsNotValidHttpsNamespace()
    {
        $this->assertNonMatchingTokenIsNotValid(...$this->getHttpsNamespaceMocks());
    }

    public function testNonMatchingTokenIsNotValidCustomNamespace()
    {
        $this->assertNonMatchingTokenIsNotValid(...$this->getCustomNamespaceMocks());
    }

    public function testNonMatchingTokenIsNotValidRequestStack()
    {
        $this->assertNonMatchingTokenIsNotValid(...$this->getRequestStackMocks());
    }

    public function testNonMatchingTokenIsNotValidClosure()
    {
        $this->assertNonMatchingTokenIsNotValid(...$this->getClosureMocks());
    }

    public function testNonMatchingTokenIsNotValidRequestStackEmptyNamespace()
    {
        $this->assertNonMatchingTokenIsNotValid(...$this->getRequestStackWithEmptyNamespaceMocks());
    }

    private function assertNonMatchingTokenIsNotValid($namespace, $manager, $storage): void
    {
        $storage->expects($this->once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage->expects($this->once())
            ->method('getToken')
            ->with($namespace.'token_id')
            ->willReturn('TOKEN');

        $this->assertFalse($manager->isTokenValid(new CsrfToken('token_id', 'FOOBAR')));
    }

    public function testNonExistingTokenIsNotValidEmptyNamespace()
    {
        $this->assertNonExistingTokenIsNotValid(...$this->getEmptyNamespaceMocks());
    }

    public function testNonExistingTokenIsNotValidHttpsNamespace()
    {
        $this->assertNonExistingTokenIsNotValid(...$this->getHttpsNamespaceMocks());
    }

    public function testNonExistingTokenIsNotValidCustomNamespace()
    {
        $this->assertNonExistingTokenIsNotValid(...$this->getCustomNamespaceMocks());
    }

    public function testNonExistingTokenIsNotValidRequestStack()
    {
        $this->assertNonExistingTokenIsNotValid(...$this->getRequestStackMocks());
    }

    public function testNonExistingTokenIsNotValidClosure()
    {
        $this->assertNonExistingTokenIsNotValid(...$this->getClosureMocks());
    }

    public function testNonExistingTokenIsNotValidRequestStackEmptyNamespace()
    {
        $this->assertNonExistingTokenIsNotValid(...$this->getRequestStackWithEmptyNamespaceMocks());
    }

    private function assertNonExistingTokenIsNotValid($namespace, $manager, $storage): void
    {
        $storage->expects($this->once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(false);

        $storage->expects($this->never())
            ->method('getToken');

        $this->assertFalse($manager->isTokenValid(new CsrfToken('token_id', 'FOOBAR')));
    }

    public function testTokenShouldNotTriggerDivisionByZero()
    {
        [$generator, $storage] = $this->getGeneratorAndStorage();
        $manager = new CsrfTokenManager($generator, $storage);

        // Scenario: the token that was returned is abc.def.ghi, and gets modified in the browser to abc..ghi

        $storage->expects($this->once())
            ->method('hasToken')
            ->with('https-token_id')
            ->willReturn(true);

        $storage->expects($this->once())
            ->method('getToken')
            ->with('https-token_id')
            ->willReturn('def');

        $this->assertFalse($manager->isTokenValid(new CsrfToken('token_id', 'abc..ghi')));
    }

    public function testRemoveTokenEmptyNamespace()
    {
        $this->assertRemoveToken(...$this->getEmptyNamespaceMocks());
    }

    public function testRemoveTokenHttpsNamespace()
    {
        $this->assertRemoveToken(...$this->getHttpsNamespaceMocks());
    }

    public function testRemoveTokenCustomNamespace()
    {
        $this->assertRemoveToken(...$this->getCustomNamespaceMocks());
    }

    public function testRemoveTokenRequestStack()
    {
        $this->assertRemoveToken(...$this->getRequestStackMocks());
    }

    public function testRemoveTokenClosure()
    {
        $this->assertRemoveToken(...$this->getClosureMocks());
    }

    public function testRemoveTokenRequestStackEmptyNamespace()
    {
        $this->assertRemoveToken(...$this->getRequestStackWithEmptyNamespaceMocks());
    }

    private function assertRemoveToken($namespace, $manager, $storage): void
    {
        $storage->expects($this->once())
            ->method('removeToken')
            ->with($namespace.'token_id')
            ->willReturn('REMOVED_TOKEN');

        $this->assertSame('REMOVED_TOKEN', $manager->removeToken('token_id'));
    }

    public function testNamespaced()
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $generator->expects($this->once())->method('generateToken')->willReturn('random');
        $storage = $this->createMock(TokenStorageInterface::class);

        $requestStack = new RequestStack();
        $requestStack->push(new Request([], [], [], [], [], ['HTTPS' => 'on']));

        $manager = new CsrfTokenManager($generator, $storage);

        $token = $manager->getToken('foo');
        $this->assertSame('foo', $token->getId());
    }

    private function getEmptyNamespaceMocks(): array
    {
        [$generator, $storage] = $this->getGeneratorAndStorage();

        return ['', new CsrfTokenManager($generator, $storage, ''), $storage, $generator];
    }

    private function getHttpsNamespaceMocks(): array
    {
        [$generator, $storage] = $this->getGeneratorAndStorage();

        return ['https-', new CsrfTokenManager($generator, $storage), $storage, $generator];
    }

    private function getCustomNamespaceMocks(): array
    {
        [$generator, $storage] = $this->getGeneratorAndStorage();

        return ['aNamespace-', new CsrfTokenManager($generator, $storage, 'aNamespace-'), $storage, $generator];
    }

    private function getRequestStackMocks(): array
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request([], [], [], [], [], ['HTTPS' => 'on']));
        [$generator, $storage] = $this->getGeneratorAndStorage();

        return ['https-', new CsrfTokenManager($generator, $storage, $requestStack), $storage, $generator];
    }

    private function getClosureMocks(): array
    {
        [$generator, $storage] = $this->getGeneratorAndStorage();

        return ['generated-', new CsrfTokenManager($generator, $storage, fn () => 'generated-'), $storage, $generator];
    }

    private function getRequestStackWithEmptyNamespaceMocks(): array
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        [$generator, $storage] = $this->getGeneratorAndStorage();

        return ['', new CsrfTokenManager($generator, $storage, $requestStack), $storage, $generator];
    }

    private function getGeneratorAndStorage(): array
    {
        return [
            $this->createMock(TokenGeneratorInterface::class),
            $this->createMock(TokenStorageInterface::class),
        ];
    }

    protected function setUp(): void
    {
        $_SERVER['HTTPS'] = 'on';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($_SERVER['HTTPS']);
    }
}
