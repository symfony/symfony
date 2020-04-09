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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\RemoteUserAuthenticator;

class RemoteUserAuthenticatorTest extends TestCase
{
    /**
     * @dataProvider provideAuthenticators
     */
    public function testSupport(UserProviderInterface $userProvider, RemoteUserAuthenticator $authenticator, $parameterName)
    {
        $request = $this->createRequest([$parameterName => 'TheUsername']);

        $this->assertTrue($authenticator->supports($request));
    }

    public function testSupportNoUser()
    {
        $authenticator = new RemoteUserAuthenticator($this->createMock(UserProviderInterface::class), new TokenStorage(), 'main');

        $this->assertFalse($authenticator->supports($this->createRequest([])));
    }

    /**
     * @dataProvider provideAuthenticators
     */
    public function testAuthenticate(UserProviderInterface $userProvider, RemoteUserAuthenticator $authenticator, $parameterName)
    {
        $request = $this->createRequest([$parameterName => 'TheUsername']);

        $authenticator->supports($request);

        $userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->with('TheUsername')
            ->willReturn($user = new User('TheUsername', null));

        $passport = $authenticator->authenticate($request);
        $this->assertEquals($user, $passport->getUser());
    }

    public function provideAuthenticators()
    {
        $userProvider = $this->createMock(UserProviderInterface::class);
        yield [$userProvider, new RemoteUserAuthenticator($userProvider, new TokenStorage(), 'main'), 'REMOTE_USER'];

        $userProvider = $this->createMock(UserProviderInterface::class);
        yield [$userProvider, new RemoteUserAuthenticator($userProvider, new TokenStorage(), 'main', 'CUSTOM_USER_PARAMETER'), 'CUSTOM_USER_PARAMETER'];
    }

    private function createRequest(array $server)
    {
        return new Request([], [], [], [], [], $server);
    }
}
