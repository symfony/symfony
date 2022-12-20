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
 */
class CsrfTokenManagerTest extends TestCase
{
    /**
     * @dataProvider getManagerGeneratorAndStorage
     */
    public function testGetNonExistingToken($namespace, $manager, $storage, $generator)
    {
        $storage->expects(self::once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(false);

        $generator->expects(self::once())
            ->method('generateToken')
            ->willReturn('TOKEN');

        $storage->expects(self::once())
            ->method('setToken')
            ->with($namespace.'token_id', 'TOKEN');

        $token = $manager->getToken('token_id');

        self::assertInstanceOf(CsrfToken::class, $token);
        self::assertSame('token_id', $token->getId());
        self::assertNotSame('TOKEN', $token->getValue());
    }

    /**
     * @dataProvider getManagerGeneratorAndStorage
     */
    public function testUseExistingTokenIfAvailable($namespace, $manager, $storage)
    {
        $storage->expects(self::once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage->expects(self::once())
            ->method('getToken')
            ->with($namespace.'token_id')
            ->willReturn('TOKEN');

        $token = $manager->getToken('token_id');

        self::assertInstanceOf(CsrfToken::class, $token);
        self::assertSame('token_id', $token->getId());
        self::assertNotSame('TOKEN', $token->getValue());
    }

    /**
     * @dataProvider getManagerGeneratorAndStorage
     */
    public function testRandomizeTheToken($namespace, $manager, $storage)
    {
        $storage->expects(self::any())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage->expects(self::any())
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

        self::assertCount(10, array_unique($values));
        self::assertGreaterThan(2, \count(array_unique($lengths)));
    }

    /**
     * @dataProvider getManagerGeneratorAndStorage
     */
    public function testRefreshTokenAlwaysReturnsNewToken($namespace, $manager, $storage, $generator)
    {
        $storage->expects(self::never())
            ->method('hasToken');

        $generator->expects(self::once())
            ->method('generateToken')
            ->willReturn('TOKEN');

        $storage->expects(self::once())
            ->method('setToken')
            ->with($namespace.'token_id', 'TOKEN');

        $token = $manager->refreshToken('token_id');

        self::assertInstanceOf(CsrfToken::class, $token);
        self::assertSame('token_id', $token->getId());
        self::assertNotSame('TOKEN', $token->getValue());
    }

    /**
     * @dataProvider getManagerGeneratorAndStorage
     */
    public function testMatchingTokenIsValid($namespace, $manager, $storage)
    {
        $storage->expects(self::exactly(2))
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage->expects(self::exactly(2))
            ->method('getToken')
            ->with($namespace.'token_id')
            ->willReturn('TOKEN');

        $token = $manager->getToken('token_id');
        self::assertNotSame('TOKEN', $token->getValue());
        self::assertTrue($manager->isTokenValid($token));
    }

    /**
     * @dataProvider getManagerGeneratorAndStorage
     */
    public function testMatchingTokenIsValidWithLegacyToken($namespace, $manager, $storage)
    {
        $storage->expects(self::once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage->expects(self::once())
            ->method('getToken')
            ->with($namespace.'token_id')
            ->willReturn('TOKEN');

        self::assertTrue($manager->isTokenValid(new CsrfToken('token_id', 'TOKEN')));
    }

    /**
     * @dataProvider getManagerGeneratorAndStorage
     */
    public function testNonMatchingTokenIsNotValid($namespace, $manager, $storage)
    {
        $storage->expects(self::once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(true);

        $storage->expects(self::once())
            ->method('getToken')
            ->with($namespace.'token_id')
            ->willReturn('TOKEN');

        self::assertFalse($manager->isTokenValid(new CsrfToken('token_id', 'FOOBAR')));
    }

    /**
     * @dataProvider getManagerGeneratorAndStorage
     */
    public function testNonExistingTokenIsNotValid($namespace, $manager, $storage)
    {
        $storage->expects(self::once())
            ->method('hasToken')
            ->with($namespace.'token_id')
            ->willReturn(false);

        $storage->expects(self::never())
            ->method('getToken');

        self::assertFalse($manager->isTokenValid(new CsrfToken('token_id', 'FOOBAR')));
    }

    public function testTokenShouldNotTriggerDivisionByZero()
    {
        [$generator, $storage] = $this->getGeneratorAndStorage();
        $manager = new CsrfTokenManager($generator, $storage);

        // Scenario: the token that was returned is abc.def.ghi, and gets modified in the browser to abc..ghi

        $storage->expects(self::once())
            ->method('hasToken')
            ->with('https-token_id')
            ->willReturn(true);

        $storage->expects(self::once())
            ->method('getToken')
            ->with('https-token_id')
            ->willReturn('def');

        self::assertFalse($manager->isTokenValid(new CsrfToken('token_id', 'abc..ghi')));
    }

    /**
     * @dataProvider getManagerGeneratorAndStorage
     */
    public function testRemoveToken($namespace, $manager, $storage)
    {
        $storage->expects(self::once())
            ->method('removeToken')
            ->with($namespace.'token_id')
            ->willReturn('REMOVED_TOKEN');

        self::assertSame('REMOVED_TOKEN', $manager->removeToken('token_id'));
    }

    public function testNamespaced()
    {
        $generator = self::createMock(TokenGeneratorInterface::class);
        $generator->expects(self::once())->method('generateToken')->willReturn('random');
        $storage = self::createMock(TokenStorageInterface::class);

        $requestStack = new RequestStack();
        $requestStack->push(new Request([], [], [], [], [], ['HTTPS' => 'on']));

        $manager = new CsrfTokenManager($generator, $storage);

        $token = $manager->getToken('foo');
        self::assertSame('foo', $token->getId());
    }

    public function getManagerGeneratorAndStorage()
    {
        $data = [];

        [$generator, $storage] = $this->getGeneratorAndStorage();
        $data[] = ['', new CsrfTokenManager($generator, $storage, ''), $storage, $generator];

        [$generator, $storage] = $this->getGeneratorAndStorage();
        $data[] = ['https-', new CsrfTokenManager($generator, $storage), $storage, $generator];

        [$generator, $storage] = $this->getGeneratorAndStorage();
        $data[] = ['aNamespace-', new CsrfTokenManager($generator, $storage, 'aNamespace-'), $storage, $generator];

        $requestStack = new RequestStack();
        $requestStack->push(new Request([], [], [], [], [], ['HTTPS' => 'on']));
        [$generator, $storage] = $this->getGeneratorAndStorage();
        $data[] = ['https-', new CsrfTokenManager($generator, $storage, $requestStack), $storage, $generator];

        [$generator, $storage] = $this->getGeneratorAndStorage();
        $data[] = ['generated-', new CsrfTokenManager($generator, $storage, function () {
            return 'generated-';
        }), $storage, $generator];

        $requestStack = new RequestStack();
        $requestStack->push(new Request());
        [$generator, $storage] = $this->getGeneratorAndStorage();
        $data[] = ['', new CsrfTokenManager($generator, $storage, $requestStack), $storage, $generator];

        return $data;
    }

    private function getGeneratorAndStorage(): array
    {
        return [
            self::createMock(TokenGeneratorInterface::class),
            self::createMock(TokenStorageInterface::class),
        ];
    }

    protected function setUp(): void
    {
        $_SERVER['HTTPS'] = 'on';
    }

    protected function tearDown(): void
    {
        self::tearDown();

        unset($_SERVER['HTTPS']);
    }
}
