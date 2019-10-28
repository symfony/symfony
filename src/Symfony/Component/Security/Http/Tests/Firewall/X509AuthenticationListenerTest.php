<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Firewall;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Firewall\X509AuthenticationListener;

class X509AuthenticationListenerTest extends TestCase
{
    /**
     * @dataProvider dataProviderGetPreAuthenticatedData
     */
    public function testGetPreAuthenticatedData($user, $credentials)
    {
        $serverVars = [];
        if ('' !== $user) {
            $serverVars['SSL_CLIENT_S_DN_Email'] = $user;
        }
        if ('' !== $credentials) {
            $serverVars['SSL_CLIENT_S_DN'] = $credentials;
        }

        $request = new Request([], [], [], [], [], $serverVars);

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();

        $listener = new X509AuthenticationListener($tokenStorage, $authenticationManager, 'TheProviderKey');

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($listener, [$request]);
        $this->assertSame($result, [$user, $credentials]);
    }

    public static function dataProviderGetPreAuthenticatedData()
    {
        return [
            'validValues' => ['TheUser', 'TheCredentials'],
            'noCredentials' => ['TheUser', ''],
        ];
    }

    /**
     * @dataProvider dataProviderGetPreAuthenticatedDataNoUser
     */
    public function testGetPreAuthenticatedDataNoUser($emailAddress, $credentials)
    {
        $request = new Request([], [], [], [], [], ['SSL_CLIENT_S_DN' => $credentials]);

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();

        $listener = new X509AuthenticationListener($tokenStorage, $authenticationManager, 'TheProviderKey');

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($listener, [$request]);
        $this->assertSame($result, [$emailAddress, $credentials]);
    }

    public static function dataProviderGetPreAuthenticatedDataNoUser()
    {
        yield ['cert@example.com', 'CN=Sample certificate DN/emailAddress=cert@example.com'];
        yield ['cert+something@example.com', 'CN=Sample certificate DN/emailAddress=cert+something@example.com'];
        yield ['cert@example.com', 'CN=Sample certificate DN,emailAddress=cert@example.com'];
        yield ['cert+something@example.com', 'CN=Sample certificate DN,emailAddress=cert+something@example.com'];
        yield ['cert+something@example.com', 'emailAddress=cert+something@example.com,CN=Sample certificate DN'];
        yield ['cert+something@example.com', 'emailAddress=cert+something@example.com'];
        yield ['firstname.lastname@mycompany.co.uk', 'emailAddress=firstname.lastname@mycompany.co.uk,CN=Firstname.Lastname,OU=london,OU=company design and engineering,OU=Issuer London,OU=Roaming,OU=Interactive,OU=Users,OU=Standard,OU=Business,DC=england,DC=core,DC=company,DC=co,DC=uk'];
    }

    public function testGetPreAuthenticatedDataNoData()
    {
        $this->expectException('Symfony\Component\Security\Core\Exception\BadCredentialsException');
        $request = new Request([], [], [], [], [], []);

        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();

        $listener = new X509AuthenticationListener($tokenStorage, $authenticationManager, 'TheProviderKey');

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $method->invokeArgs($listener, [$request]);
    }

    public function testGetPreAuthenticatedDataWithDifferentKeys()
    {
        $userCredentials = ['TheUser', 'TheCredentials'];

        $request = new Request([], [], [], [], [], [
            'TheUserKey' => 'TheUser',
            'TheCredentialsKey' => 'TheCredentials',
        ]);
        $tokenStorage = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface')->getMock();

        $authenticationManager = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface')->getMock();

        $listener = new X509AuthenticationListener($tokenStorage, $authenticationManager, 'TheProviderKey', 'TheUserKey', 'TheCredentialsKey');

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($listener, [$request]);
        $this->assertSame($result, $userCredentials);
    }
}
