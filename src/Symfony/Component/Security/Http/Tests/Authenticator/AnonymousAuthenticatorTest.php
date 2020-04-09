<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\AnonymousAuthenticator;

class AnonymousAuthenticatorTest extends TestCase
{
    private $tokenStorage;
    private $authenticator;
    private $request;

    protected function setUp(): void
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->authenticator = new AnonymousAuthenticator('s3cr3t', $this->tokenStorage);
        $this->request = new Request();
    }

    /**
     * @dataProvider provideSupportsData
     */
    public function testSupports($tokenAlreadyAvailable, $result)
    {
        $this->tokenStorage->expects($this->any())->method('getToken')->willReturn($tokenAlreadyAvailable ? $this->createMock(TokenStorageInterface::class) : null);

        $this->assertEquals($result, $this->authenticator->supports($this->request));
    }

    public function provideSupportsData()
    {
        yield [true, null];
        yield [false, false];
    }

    public function testAuthenticatedToken()
    {
        $token = $this->authenticator->createAuthenticatedToken($this->authenticator->authenticate($this->request), 'main');

        $this->assertTrue($token->isAuthenticated());
        $this->assertEquals('anon.', $token->getUser());
    }
}
