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
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\Authenticator\X509Authenticator;

class X509AuthenticatorTest extends TestCase
{
    private $userProvider;
    private $authenticator;

    protected function setUp(): void
    {
        $this->userProvider = new InMemoryUserProvider();
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

        $this->userProvider->createUser(new InMemoryUser($username, null));

        $passport = $this->authenticator->authenticate($request);
        $this->assertEquals($username, $passport->getUser()->getUserIdentifier());
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

        $this->userProvider->createUser(new InMemoryUser($emailAddress, null));

        $passport = $this->authenticator->authenticate($request);
        $this->assertEquals($emailAddress, $passport->getUser()->getUserIdentifier());
    }

    public static function provideServerVarsNoUser()
    {
        yield ['cert@example.com', '/CN=Sample certificate DN/emailAddress=cert@example.com'];
        yield ['cert+something@example.com', '/CN=Sample certificate DN/emailAddress=cert+something@example.com'];
        yield ['cert@example.com', 'CN=Sample certificate DN,emailAddress=cert@example.com'];
        yield ['cert+something@example.com', 'CN=Sample certificate DN,emailAddress=cert+something@example.com'];
        yield ['cert+something@example.com', 'emailAddress=cert+something@example.com,CN=Sample certificate DN'];
        yield ['cert+something@example.com', 'emailAddress=cert+something@example.com'];
        yield ['firstname.lastname@mycompany.co.uk', 'emailAddress=firstname.lastname@mycompany.co.uk,CN=Firstname.Lastname,OU=london,OU=company design and engineering,OU=Issuer London,OU=Roaming,OU=Interactive,OU=Users,OU=Standard,OU=Business,DC=england,DC=core,DC=company,DC=co,DC=uk'];
        yield ['cert@example.com', '1.2.840.113549.1.9.1=cert@example.com,CN=Sample certificate DN'];
        yield ['cert@example.com', '/CN=Sample certificate DN/1.2.840.113549.1.9.1=cert@example.com'];

        yield 'escaped separator in a preceding value' => ['cert@example.com', 'CN=Sample\, certificate DN,emailAddress=cert@example.com'];
        yield 'genuine attribute wins over one embedded in a CN' => ['cert@example.com', 'CN=jdoe\,emailAddress=admin@target.example,emailAddress=cert@example.com'];
        yield 'two genuine attributes' => ['first@example.com', 'emailAddress=first@example.com,emailAddress=second@example.com,CN=Sample certificate DN'];
        yield 'slash inside a RFC 2253 value' => ['cert@example.com', 'CN=Sample/certificate DN,emailAddress=cert@example.com'];
        yield 'escaped separator in a preceding value, legacy format' => ['cert@example.com', '/CN=Sample\/certificate DN/emailAddress=cert@example.com'];
        yield 'hex escape in a preceding value' => ['cert@example.com', 'CN=Sample\2C certificate DN,emailAddress=cert@example.com'];
        yield 'hex escape in a preceding legacy value' => ['cert@example.com', '/CN=Sample\\x2Fcertificate DN/emailAddress=cert@example.com'];
        yield 'quoted preceding value' => ['cert@example.com', 'CN="Sample, certificate DN",emailAddress=cert@example.com'];
    }

    /**
     * @dataProvider provideSpoofedDns
     */
    public function testAuthenticationRejectsSpoofedEmailInDn(string $dn)
    {
        $request = $this->createRequest(['SSL_CLIENT_S_DN' => $dn]);

        $this->assertFalse($this->authenticator->supports($request));
    }

    public static function provideSpoofedDns()
    {
        yield ['CN=emailAddress=admin@target.example,O=Attacker Corp'];
        yield ['CN=foo,OU=emailAddress=admin@target.example,O=Attacker'];
        yield ['CN=1.2.840.113549.1.9.1=admin@target.example,O=Attacker Corp'];
        yield ['CN=foo,OU=1.2.840.113549.1.9.1=admin@target.example,O=Attacker'];

        yield 'escaped comma' => ['CN=jdoe\,emailAddress=admin@target.example'];
        yield 'escaped comma, OID' => ['CN=jdoe\,1.2.840.113549.1.9.1=admin@target.example'];
        yield 'escaped comma before a legitimate attribute' => ['CN=jdoe\,emailAddress=admin@target.example,O=Attacker'];
        yield 'hex escaped comma' => ['CN=jdoe\2CemailAddress=admin@target.example'];
        yield 'slash in a RFC 2253 value' => ['CN=jdoe/emailAddress=admin@target.example'];
        yield 'escaped slash in a legacy value' => ['/CN=jdoe\/emailAddress=admin@target.example'];
        yield 'comma in a legacy value' => ['/CN=jdoe,emailAddress=admin@target.example'];
        yield 'backslash then escaped slash in a legacy value' => ['/CN=jdoe\\\\/emailAddress=admin@target.example'];
        yield 'quoted value' => ['CN="jdoe,emailAddress=admin@target.example,x=",O=Attacker'];
        yield 'quoted value, XN_FLAG_ONELINE' => ['O = Attacker, CN = "jdoe,emailAddress=admin@target.example,x="'];

        yield 'backslash then escaped slash in a legacy value before the genuine attribute' => ['/CN=jdoe\\\\/emailAddress=admin@target.example/emailAddress=cert@example.com'];

        // OpenSSL before 3.0 and HAProxy leave slashes inside values unescaped
        yield 'bare slash in a legacy value' => ['/C=FR/O=Acme/CN=jdoe/emailAddress=admin@target.example/emailAddress=cert@example.com'];
        yield 'bare slash in a legacy value, OID' => ['/CN=jdoe/1.2.840.113549.1.9.1=admin@target.example/emailAddress=cert@example.com'];
        yield 'bare slash in a legacy value ending with a backslash' => ['/CN=jdoe/emailAddress=admin@target.example/x=\\/emailAddress=cert@example.com'];
        yield 'bare slash in a legacy value, hex escaped attribute' => ['/CN=jdoe/\\x65mailAddress=admin@target.example/emailAddress=cert@example.com'];
    }

    public function testSupportNoData()
    {
        $request = $this->createRequest([]);

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function testSupportNonStringCredentials()
    {
        $request = $this->createRequest(['SSL_CLIENT_S_DN' => null]);

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function testAuthenticationCustomUserKey()
    {
        $authenticator = new X509Authenticator($this->userProvider, new TokenStorage(), 'main', 'TheUserKey');

        $request = $this->createRequest([
            'TheUserKey' => 'TheUser',
        ]);
        $this->assertTrue($authenticator->supports($request));

        $this->userProvider->createUser(new InMemoryUser('TheUser', null));

        $passport = $this->authenticator->authenticate($request);
        $this->assertEquals('TheUser', $passport->getUser()->getUserIdentifier());
    }

    public function testAuthenticationCustomCredentialsKey()
    {
        $authenticator = new X509Authenticator($this->userProvider, new TokenStorage(), 'main', 'SSL_CLIENT_S_DN_Email', 'TheCertKey');

        $request = $this->createRequest([
            'TheCertKey' => '/CN=Sample certificate DN/emailAddress=cert@example.com',
        ]);
        $this->assertTrue($authenticator->supports($request));

        $this->userProvider->createUser(new InMemoryUser('cert@example.com', null));

        $passport = $authenticator->authenticate($request);
        $this->assertEquals('cert@example.com', $passport->getUser()->getUserIdentifier());
    }

    private function createRequest(array $server)
    {
        return new Request([], [], [], [], [], $server);
    }
}
