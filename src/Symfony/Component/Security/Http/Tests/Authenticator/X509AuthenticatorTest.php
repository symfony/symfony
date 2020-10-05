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
use Symfony\Component\Security\Http\Authenticator\X509Authenticator;

class X509AuthenticatorTest extends TestCase
{
    private $userProvider;
    private $authenticator;

    protected function setUp(): void
    {
        $this->userProvider = $this->createMock(UserProviderInterface::class);
        $this->authenticator = new X509Authenticator($this->userProvider, new TokenStorage(), 'main');
    }

    /**
     * @dataProvider provideServerVars
     */
    public function testAuthentication($username, $credentials)
    {
        $serverVars = [];
        if ('' !== $username) {
            $serverVars['SSL_CLIENT_S_DN_Email'] = $username;
        }
        if ('' !== $credentials) {
            $serverVars['SSL_CLIENT_S_DN'] = $credentials;
        }

        $request = $this->createRequest($serverVars);
        $this->assertTrue($this->authenticator->supports($request));

        $this->userProvider->expects($this->any())
            ->method('loadUserByUsername')
            ->with($username)
            ->willReturn(new User($username, null));

        $passport = $this->authenticator->authenticate($request);
        $this->assertEquals($username, $passport->getUser()->getUsername());
    }

    public static function provideServerVars()
    {
        yield ['TheUser', 'TheCredentials'];
        yield ['TheUser', ''];
    }

    /**
     * @dataProvider provideServerVarsNoUser
     */
    public function testAuthenticationNoUser($emailAddress, $credentials)
    {
        $request = $this->createRequest(['SSL_CLIENT_S_DN' => $credentials]);

        $this->assertTrue($this->authenticator->supports($request));

        $this->userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->with($emailAddress)
            ->willReturn(new User($emailAddress, null));

        $passport = $this->authenticator->authenticate($request);
        $this->assertEquals($emailAddress, $passport->getUser()->getUsername());
    }

    public static function provideServerVarsNoUser()
    {
        yield ['cert@example.com', 'CN=Sample certificate DN/emailAddress=cert@example.com'];
        yield ['cert+something@example.com', 'CN=Sample certificate DN/emailAddress=cert+something@example.com'];
        yield ['cert@example.com', 'CN=Sample certificate DN,emailAddress=cert@example.com'];
        yield ['cert+something@example.com', 'CN=Sample certificate DN,emailAddress=cert+something@example.com'];
        yield ['cert+something@example.com', 'emailAddress=cert+something@example.com,CN=Sample certificate DN'];
        yield ['cert+something@example.com', 'emailAddress=cert+something@example.com'];
        yield ['firstname.lastname@mycompany.co.uk', 'emailAddress=firstname.lastname@mycompany.co.uk,CN=Firstname.Lastname,OU=london,OU=company design and engineering,OU=Issuer London,OU=Roaming,OU=Interactive,OU=Users,OU=Standard,OU=Business,DC=england,DC=core,DC=company,DC=co,DC=uk'];
    }

    public function testSupportNoData()
    {
        $request = $this->createRequest([]);

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function testAuthenticationCustomUserKey()
    {
        $authenticator = new X509Authenticator($this->userProvider, new TokenStorage(), 'main', 'TheUserKey');

        $request = $this->createRequest([
            'TheUserKey' => 'TheUser',
        ]);
        $this->assertTrue($authenticator->supports($request));

        $this->userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->with('TheUser')
            ->willReturn(new User('TheUser', null));

        $passport = $this->authenticator->authenticate($request);
        $this->assertEquals('TheUser', $passport->getUser()->getUsername());
    }

    public function testAuthenticationCustomCredentialsKey()
    {
        $authenticator = new X509Authenticator($this->userProvider, new TokenStorage(), 'main', 'SSL_CLIENT_S_DN_Email', 'TheCertKey');

        $request = $this->createRequest([
            'TheCertKey' => 'CN=Sample certificate DN/emailAddress=cert@example.com',
        ]);
        $this->assertTrue($authenticator->supports($request));

        $this->userProvider->expects($this->once())
            ->method('loadUserByUsername')
            ->with('cert@example.com')
            ->willReturn(new User('cert@example.com', null));

        $passport = $authenticator->authenticate($request);
        $this->assertEquals('cert@example.com', $passport->getUser()->getUsername());
    }

    private function createRequest(array $server)
    {
        return new Request([], [], [], [], [], $server);
    }
}
