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
    public function testGetNonExistingTokenEmptyNamespace(): void
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertGetNonExistingToken('', $this->getEmptyNamespaceMocks($generator, $storage), $storage, $generator);
    }

    public function testGetNonExistingTokenHttpsNamespace(): void
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertGetNonExistingToken('https-', $this->getHttpsNamespaceMocks($generator, $storage), $storage, $generator);
    }

    public function testGetNonExistingTokenCustomNamespace(): void
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertGetNonExistingToken('aNamespace-', $this->getCustomNamespaceMocks($generator, $storage), $storage, $generator);
    }

    public function testGetNonExistingTokenRequestStack(): void
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertGetNonExistingToken('https-', $this->getRequestStackMocks($generator, $storage), $storage, $generator);
    }

    public function testGetNonExistingTokenClosure(): void
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertGetNonExistingToken('generated-', $this->getClosureMocks($generator, $storage), $storage, $generator);
    }

    public function testGetNonExistingTokenRequestStackEmptyNamespace(): void
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertGetNonExistingToken('', $this->getRequestStackWithEmptyNamespaceMocks($generator, $storage), $storage, $generator);
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

    public function testUseExistingTokenIfAvailableEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertUseExistingTokenIfAvailable('', $this->getEmptyNamespaceMocks($generator, $storage), $storage);
    }

    public function testUseExistingTokenIfAvailableHttpsNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertUseExistingTokenIfAvailable('https-', $this->getHttpsNamespaceMocks($generator, $storage), $storage);
    }

    public function testUseExistingTokenIfAvailableCustomNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertUseExistingTokenIfAvailable('aNamespace-', $this->getCustomNamespaceMocks($generator, $storage), $storage);
    }

    public function testUseExistingTokenIfAvailableRequestStack(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertUseExistingTokenIfAvailable('https-', $this->getRequestStackMocks($generator, $storage), $storage);
    }

    public function testUseExistingTokenIfAvailableClosure(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertUseExistingTokenIfAvailable('generated-', $this->getClosureMocks($generator, $storage), $storage);
    }

    public function testUseExistingTokenIfAvailableRequestStackEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertUseExistingTokenIfAvailable('', $this->getRequestStackWithEmptyNamespaceMocks($generator, $storage), $storage);
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

    public function testRandomizeTheTokenEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createStub(TokenStorageInterface::class);

        $this->assertRandomizeTheToken('', $this->getEmptyNamespaceMocks($generator, $storage), $storage);
    }

    public function testRandomizeTheTokenHttpsNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createStub(TokenStorageInterface::class);

        $this->assertRandomizeTheToken('https-', $this->getHttpsNamespaceMocks($generator, $storage), $storage);
    }

    public function testRandomizeTheTokenCustomNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createStub(TokenStorageInterface::class);

        $this->assertRandomizeTheToken('aNamespace-', $this->getCustomNamespaceMocks($generator, $storage), $storage);
    }

    public function testRandomizeTheTokenRequestStack(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createStub(TokenStorageInterface::class);

        $this->assertRandomizeTheToken('https-', $this->getRequestStackMocks($generator, $storage), $storage);
    }

    public function testRandomizeTheTokenClosure(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createStub(TokenStorageInterface::class);

        $this->assertRandomizeTheToken('generated-', $this->getClosureMocks($generator, $storage), $storage);
    }

    public function testRandomizeTheTokenRequestStackEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createStub(TokenStorageInterface::class);

        $this->assertRandomizeTheToken('', $this->getRequestStackWithEmptyNamespaceMocks($generator, $storage), $storage);
    }

    private function assertRandomizeTheToken($namespace, $manager, $storage): void
    {
        $storage
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage
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

    public function testRefreshTokenAlwaysReturnsNewTokenEmptyNamespace(): void
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertRefreshTokenAlwaysReturnsNewToken('', $this->getEmptyNamespaceMocks($generator, $storage), $storage, $generator);
    }

    public function testRefreshTokenAlwaysReturnsNewTokenHttpsNamespace(): void
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertRefreshTokenAlwaysReturnsNewToken('https-', $this->getHttpsNamespaceMocks($generator, $storage), $storage, $generator);
    }

    public function testRefreshTokenAlwaysReturnsNewTokenCustomNamespace(): void
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertRefreshTokenAlwaysReturnsNewToken('aNamespace-', $this->getCustomNamespaceMocks($generator, $storage), $storage, $generator);
    }

    public function testRefreshTokenAlwaysReturnsNewTokenRequestStack(): void
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertRefreshTokenAlwaysReturnsNewToken('https-', $this->getRequestStackMocks($generator, $storage), $storage, $generator);
    }

    public function testRefreshTokenAlwaysReturnsNewTokenClosure(): void
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertRefreshTokenAlwaysReturnsNewToken('generated-', $this->getClosureMocks($generator, $storage), $storage, $generator);
    }

    public function testRefreshTokenAlwaysReturnsNewTokenRequestStackEmptyNamespace(): void
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertRefreshTokenAlwaysReturnsNewToken('', $this->getRequestStackWithEmptyNamespaceMocks($generator, $storage), $storage, $generator);
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

    public function testMatchingTokenIsValidEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertMatchingTokenIsValid('', $this->getEmptyNamespaceMocks($generator, $storage), $storage);
    }

    public function testMatchingTokenIsValidHttpsNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertMatchingTokenIsValid('https-', $this->getHttpsNamespaceMocks($generator, $storage), $storage);
    }

    public function testMatchingTokenIsValidCustomNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertMatchingTokenIsValid('aNamespace-', $this->getCustomNamespaceMocks($generator, $storage), $storage);
    }

    public function testMatchingTokenIsValidRequestStack(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertMatchingTokenIsValid('https-', $this->getRequestStackMocks($generator, $storage), $storage);
    }

    public function testMatchingTokenIsValidClosure(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertMatchingTokenIsValid('generated-', $this->getClosureMocks($generator, $storage), $storage);
    }

    public function testMatchingTokenIsValidRequestStackEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertMatchingTokenIsValid('', $this->getRequestStackWithEmptyNamespaceMocks($generator, $storage), $storage);
    }

    private function assertMatchingTokenIsValid($namespace, $manager, $storage): void
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

    public function testMatchingTokenIsValidWithLegacyTokenEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertMatchingTokenIsValidWithLegacyToken('', $this->getEmptyNamespaceMocks($generator, $storage), $storage);
    }

    public function testMatchingTokenIsValidWithLegacyTokenHttpsNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertMatchingTokenIsValidWithLegacyToken('https-', $this->getHttpsNamespaceMocks($generator, $storage), $storage);
    }

    public function testMatchingTokenIsValidWithLegacyTokenCustomNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertMatchingTokenIsValidWithLegacyToken('aNamespace-', $this->getCustomNamespaceMocks($generator, $storage), $storage);
    }

    public function testMatchingTokenIsValidWithLegacyTokenRequestStack(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertMatchingTokenIsValidWithLegacyToken('https-', $this->getRequestStackMocks($generator, $storage), $storage);
    }

    public function testMatchingTokenIsValidWithLegacyTokenClosure(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertMatchingTokenIsValidWithLegacyToken('generated-', $this->getClosureMocks($generator, $storage), $storage);
    }

    public function testMatchingTokenIsValidWithLegacyTokenRequestStackEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertMatchingTokenIsValidWithLegacyToken('', $this->getRequestStackWithEmptyNamespaceMocks($generator, $storage), $storage);
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

    public function testNonMatchingTokenIsNotValidEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertNonMatchingTokenIsNotValid('', $this->getEmptyNamespaceMocks($generator, $storage), $storage);
    }

    public function testNonMatchingTokenIsNotValidHttpsNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertNonMatchingTokenIsNotValid('https-', $this->getHttpsNamespaceMocks($generator, $storage), $storage);
    }

    public function testNonMatchingTokenIsNotValidCustomNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertNonMatchingTokenIsNotValid('aNamespace-', $this->getCustomNamespaceMocks($generator, $storage), $storage);
    }

    public function testNonMatchingTokenIsNotValidRequestStack(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertNonMatchingTokenIsNotValid('https-', $this->getRequestStackMocks($generator, $storage), $storage);
    }

    public function testNonMatchingTokenIsNotValidClosure(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertNonMatchingTokenIsNotValid('generated-', $this->getClosureMocks($generator, $storage), $storage);
    }

    public function testNonMatchingTokenIsNotValidRequestStackEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertNonMatchingTokenIsNotValid('', $this->getRequestStackWithEmptyNamespaceMocks($generator, $storage), $storage);
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

    public function testNonExistingTokenIsNotValidEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertNonExistingTokenIsNotValid('', $this->getEmptyNamespaceMocks($generator, $storage), $storage);
    }

    public function testNonExistingTokenIsNotValidHttpsNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertNonExistingTokenIsNotValid('https-', $this->getHttpsNamespaceMocks($generator, $storage), $storage);
    }

    public function testNonExistingTokenIsNotValidCustomNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertNonExistingTokenIsNotValid('aNamespace-', $this->getCustomNamespaceMocks($generator, $storage), $storage);
    }

    public function testNonExistingTokenIsNotValidRequestStack(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertNonExistingTokenIsNotValid('https-', $this->getRequestStackMocks($generator, $storage), $storage);
    }

    public function testNonExistingTokenIsNotValidClosure(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertNonExistingTokenIsNotValid('generated-', $this->getClosureMocks($generator, $storage), $storage);
    }

    public function testNonExistingTokenIsNotValidRequestStackEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertNonExistingTokenIsNotValid('', $this->getRequestStackWithEmptyNamespaceMocks($generator, $storage), $storage);
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

    public function testTokenShouldNotTriggerDivisionByZero(): void
    {
        $storage = $this->createMock(TokenStorageInterface::class);
        $manager = new CsrfTokenManager($this->createStub(TokenGeneratorInterface::class), $storage);

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

    public function testRemoveTokenEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertRemoveToken('', $this->getEmptyNamespaceMocks($generator, $storage), $storage);
    }

    public function testRemoveTokenHttpsNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertRemoveToken('https-', $this->getHttpsNamespaceMocks($generator, $storage), $storage);
    }

    public function testRemoveTokenCustomNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertRemoveToken('aNamespace-', $this->getCustomNamespaceMocks($generator, $storage), $storage);
    }

    public function testRemoveTokenRequestStack(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertRemoveToken('https-', $this->getRequestStackMocks($generator, $storage), $storage);
    }

    public function testRemoveTokenClosure(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertRemoveToken('generated-', $this->getClosureMocks($generator, $storage), $storage);
    }

    public function testRemoveTokenRequestStackEmptyNamespace(): void
    {
        $generator = $this->createStub(TokenGeneratorInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);

        $this->assertRemoveToken('', $this->getRequestStackWithEmptyNamespaceMocks($generator, $storage), $storage);
    }

    private function assertRemoveToken($namespace, $manager, $storage): void
    {
        $storage->expects($this->once())
            ->method('removeToken')
            ->with($namespace.'token_id')
            ->willReturn('REMOVED_TOKEN');

        $this->assertSame('REMOVED_TOKEN', $manager->removeToken('token_id'));
    }

    public function testNamespaced(): void
    {
        $generator = $this->createMock(TokenGeneratorInterface::class);
        $generator->expects($this->once())->method('generateToken')->willReturn('random');

        $requestStack = new RequestStack();
        $requestStack->push(new Request([], [], [], [], [], ['HTTPS' => 'on']));

        $manager = new CsrfTokenManager($generator, $this->createStub(TokenStorageInterface::class));

        $token = $manager->getToken('foo');
        $this->assertSame('foo', $token->getId());
    }

    private function getEmptyNamespaceMocks(TokenGeneratorInterface $generator, TokenStorageInterface $storage): CsrfTokenManager
    {
        return new CsrfTokenManager($generator, $storage, '');
    }

    private function getHttpsNamespaceMocks(TokenGeneratorInterface $generator, TokenStorageInterface $storage): CsrfTokenManager
    {
        return new CsrfTokenManager($generator, $storage);
    }

    private function getCustomNamespaceMocks(TokenGeneratorInterface $generator, TokenStorageInterface $storage): CsrfTokenManager
    {
        return new CsrfTokenManager($generator, $storage, 'aNamespace-');
    }

    private function getRequestStackMocks(TokenGeneratorInterface $generator, TokenStorageInterface $storage): CsrfTokenManager
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request([], [], [], [], [], ['HTTPS' => 'on']));

        return new CsrfTokenManager($generator, $storage, $requestStack);
    }

    private function getClosureMocks(TokenGeneratorInterface $generator, TokenStorageInterface $storage): CsrfTokenManager
    {
        return new CsrfTokenManager($generator, $storage, static fn () => 'generated-');
    }

    private function getRequestStackWithEmptyNamespaceMocks(TokenGeneratorInterface $generator, TokenStorageInterface $storage): CsrfTokenManager
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        return new CsrfTokenManager($generator, $storage, $requestStack);
    }

    protected function setUp(): void
    {
        $_SERVER['HTTPS'] = 'on';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTPS']);
    }
}
