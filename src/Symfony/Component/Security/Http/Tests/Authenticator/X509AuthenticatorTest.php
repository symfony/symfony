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
    private InMemoryUserProvider $userProvider;
    private X509Authenticator $authenticator;

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
        yield 'slash inside a RFC 2253 value' => ['cert@example.com', 'CN=Sample/certificate DN,emailAddress=cert@example.com'];
        yield 'escaped separator in a preceding value, legacy format' => ['cert@example.com', '/CN=Sample\/certificate DN/emailAddress=cert@example.com'];
        yield 'hex escape in a preceding value' => ['cert@example.com', 'CN=Sample\2C certificate DN,emailAddress=cert@example.com'];
        yield 'backslash then escaped slash in a preceding legacy value' => ['cert@example.com', '/CN=jdoe\\\\/emailAddress=admin@target.example/emailAddress=cert@example.com'];
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

    /**
     * @dataProvider provideServerVarsUserIdentifier
     */
    public function testAuthenticationCustomCredentialsUserIdentifier($username, $credentials)
    {
        $authenticator = new X509Authenticator($this->userProvider, new TokenStorage(), 'main', 'SSL_CLIENT_S_DN_Email', 'SSL_CLIENT_S_DN', null, 'CN');

        $request = $this->createRequest([
            'SSL_CLIENT_S_DN' => $credentials,
        ]);
        $this->assertTrue($authenticator->supports($request));

        $this->userProvider->createUser(new InMemoryUser($username, null));

        $passport = $authenticator->authenticate($request);
        $this->assertEquals($username, $passport->getUser()->getUserIdentifier());
    }

    public static function provideServerVarsUserIdentifier()
    {
        yield ['Sample certificate DN', '/CN=Sample certificate DN/emailAddress=cert@example.com'];
        yield ['Sample certificate DN', '/CN=Sample certificate DN/emailAddress=cert+something@example.com'];
        yield ['Sample certificate DN', 'CN=Sample certificate DN,emailAddress=cert@example.com'];
        yield ['Sample certificate DN', 'CN=Sample certificate DN,emailAddress=cert+something@example.com'];
        yield ['Sample certificate DN', 'emailAddress=cert+something@example.com,CN=Sample certificate DN'];
        yield ['Firstname.Lastname', 'emailAddress=firstname.lastname@mycompany.co.uk,CN=Firstname.Lastname,OU=london,OU=company design and engineering,OU=Issuer London,OU=Roaming,OU=Interactive,OU=Users,OU=Standard,OU=Business,DC=england,DC=core,DC=company,DC=co,DC=uk'];
        yield ['user1', 'C=FR, O=My Organization, CN=user1, emailAddress=user1@myorg.fr'];
    }

    /**
     * @dataProvider provideDnsForCustomUserIdentifier
     */
    public function testAuthenticationCustomCredentialsUserIdentifierSpansAWholeRdn(?string $username, string $dn)
    {
        $authenticator = new X509Authenticator($this->userProvider, new TokenStorage(), 'main', 'SSL_CLIENT_S_DN_Email', 'SSL_CLIENT_S_DN', null, 'UID');

        $request = $this->createRequest(['SSL_CLIENT_S_DN' => $dn]);

        if (null === $username) {
            $this->assertFalse($authenticator->supports($request));

            return;
        }

        $this->assertTrue($authenticator->supports($request));

        $this->userProvider->createUser(new InMemoryUser($username, null));

        $passport = $authenticator->authenticate($request);
        $this->assertEquals($username, $passport->getUser()->getUserIdentifier());
    }

    public static function provideDnsForCustomUserIdentifier()
    {
        yield 'escaped comma in an O' => [null, 'C=US,O=Evil Co\,UID=admin,CN=RealAttacker'];
        yield 'comma in a legacy O' => [null, '/C=US/O=Evil Co,UID=admin/CN=RealAttacker'];
        yield 'slash in a RFC 2253 value' => [null, 'C=US,O=Evil Co/UID=admin,CN=RealAttacker'];
        yield 'attribute embedded in a CN' => [null, 'CN=UID=admin,O=Attacker Corp'];
        yield 'quoted O' => [null, 'C=US,O="Evil Co,UID=admin",CN=RealAttacker'];

        yield 'genuine attribute' => ['jdoe', 'C=US,O=Ex,UID=jdoe,CN=John Doe'];
        yield 'genuine attribute, legacy format' => ['jdoe', '/C=US/O=Ex/UID=jdoe/CN=John Doe'];
        yield 'genuine attribute wins over one embedded in an O' => ['jdoe', 'C=US,O=Evil Co\,UID=admin,UID=jdoe'];
        yield 'surrounding spaces are trimmed' => ['jdoe', 'C=US, UID=jdoe , CN=John Doe'];
    }

    /**
     * @dataProvider provideDns
     */
    public function testSplitDn(array $expected, string $dn)
    {
        $method = new \ReflectionMethod(X509Authenticator::class, 'splitDn');

        $this->assertSame($expected, $method->invoke(null, $dn));
    }

    /**
     * The serialized DNs are the output of X509_NAME_print_ex() with XN_FLAG_RFC2253 and of
     * X509_NAME_oneline() from OpenSSL 3, for certificates whose CN holds the listed value.
     */
    public static function provideDns()
    {
        yield 'empty' => [[''], ''];
        yield 'single RDN' => [['emailAddress=cert@example.com'], 'emailAddress=cert@example.com'];
        yield 'RFC 2253' => [['CN=jdoe', 'O=Ex', 'emailAddress=jdoe@example.com'], 'CN=jdoe,O=Ex,emailAddress=jdoe@example.com'];
        yield 'legacy' => [['emailAddress=jdoe@example.com', 'O=Ex', 'CN=jdoe'], '/emailAddress=jdoe@example.com/O=Ex/CN=jdoe'];
        yield 'legacy, one RDN' => [['CN=jdoe'], '/CN=jdoe'];
        yield 'legacy, empty' => [[''], '/'];

        // CN "jdoe,emailAddress=admin@example.com"
        yield 'RFC 2253, comma in a value' => [['CN=jdoe,emailAddress=admin@example.com', 'O=Ex'], 'CN=jdoe\,emailAddress=admin@example.com,O=Ex'];
        yield 'legacy, comma in a value' => [['O=Ex', 'CN=jdoe,emailAddress=admin@example.com'], '/O=Ex/CN=jdoe,emailAddress=admin@example.com'];

        // CN "jdoe/emailAddress=admin@example.com"
        yield 'RFC 2253, slash in a value' => [['CN=jdoe/emailAddress=admin@example.com', 'O=Ex'], 'CN=jdoe/emailAddress=admin@example.com,O=Ex'];
        yield 'legacy, slash in a value' => [['O=Ex', 'CN=jdoe/emailAddress=admin@example.com'], '/O=Ex/CN=jdoe\/emailAddress=admin@example.com'];

        // CN "jdoe+emailAddress=admin@example.com"
        yield 'RFC 2253, plus in a value' => [['CN=jdoe+emailAddress=admin@example.com', 'O=Ex'], 'CN=jdoe\+emailAddress=admin@example.com,O=Ex'];
        yield 'legacy, plus in a value' => [['O=Ex', 'CN=jdoe+emailAddress=admin@example.com'], '/O=Ex/CN=jdoe\+emailAddress=admin@example.com'];

        // CN "jdoe\,emailAddress=admin@example.com"
        yield 'RFC 2253, backslash and comma in a value' => [['CN=jdoe\,emailAddress=admin@example.com', 'O=Ex'], 'CN=jdoe\\\\\,emailAddress=admin@example.com,O=Ex'];
        yield 'legacy, backslash and comma in a value' => [['O=Ex', 'CN=jdoe\,emailAddress=admin@example.com'], '/O=Ex/CN=jdoe\,emailAddress=admin@example.com'];

        // CN "jdoe\/emailAddress=admin@example.com"
        yield 'RFC 2253, backslash and slash in a value' => [['CN=jdoe\/emailAddress=admin@example.com', 'O=Ex'], 'CN=jdoe\\\\/emailAddress=admin@example.com,O=Ex'];
        yield 'legacy, backslash and slash in a value' => [['O=Ex', 'CN=jdoe\/emailAddress=admin@example.com'], '/O=Ex/CN=jdoe\\\\/emailAddress=admin@example.com'];

        // CN "C:\"
        yield 'RFC 2253, value ending with a backslash' => [['CN=C:\\', 'O=Ex'], 'CN=C:\\\\,O=Ex'];
        yield 'legacy, value ending with a backslash' => [['O=Ex', 'CN=C:\\'], '/O=Ex/CN=C:\\'];
        yield 'legacy, value ending with a backslash before a separator' => [['CN=C:\/O=Ex'], '/CN=C:\\\\/O=Ex'];

        // CN "jdoe\x2FemailAddress=admin@example.com"
        yield 'RFC 2253, backslash x in a value' => [['CN=jdoe\x2FemailAddress=admin@example.com', 'O=Ex'], 'CN=jdoe\\\\x2FemailAddress=admin@example.com,O=Ex'];

        // CN "José tab\there"
        yield 'RFC 2253, hex escapes' => [['CN=José tab'."\t".'here', 'O=Ex'], 'CN=Jos\C3\A9 tab\09here,O=Ex'];
        yield 'RFC 2253, hex escapes, lowercase' => [['CN=José', 'O=Ex'], 'CN=Jos\c3\a9,O=Ex'];
        yield 'legacy, hex escapes' => [['O=Ex', 'CN=José tab'."\t".'here'], '/O=Ex/CN=Jos\xC3\xA9 tab\x09here'];
        yield 'legacy, hex escape of a slash' => [['O=Ex', 'CN=jdoe/emailAddress=admin@example.com'], '/O=Ex/CN=jdoe\x2FemailAddress=admin@example.com'];
        yield 'legacy, backslash x without hex digits' => [['O=Ex', 'CN=jdoe\xZZ'], '/O=Ex/CN=jdoe\xZZ'];

        yield 'RFC 2253, trailing backslash' => [['CN=jdoe\\'], 'CN=jdoe\\'];
        yield 'legacy, trailing backslash' => [['CN=jdoe\\'], '/CN=jdoe\\'];
        yield 'RFC 2253, escaped separator at the end' => [['CN=jdoe,'], 'CN=jdoe\,'];
        yield 'RFC 2253, empty RDNs' => [['', 'CN=jdoe', ''], ',CN=jdoe,'];
        yield 'RFC 2253, spaces are kept' => [['CN=jdoe', ' O=Ex'], 'CN=jdoe, O=Ex'];
        yield 'multi-valued RDN is kept whole' => [['O=Ex', 'emailAddress=jdoe@example.com+CN=jdoe'], 'O=Ex,emailAddress=jdoe@example.com+CN=jdoe'];

        // CN "jdoe,emailAddress=admin@example.com", from formats that quote values instead of escaping their characters
        yield 'quoted value' => [['CN=jdoe,emailAddress=admin@example.com', 'O=Ex'], 'CN="jdoe,emailAddress=admin@example.com",O=Ex'];
        yield 'quoted value, XN_FLAG_ONELINE' => [['O = Ex', ' CN = jdoe,emailAddress=admin@example.com'], 'O = Ex, CN = "jdoe,emailAddress=admin@example.com"'];

        // CN "jdoe\",emailAddress=admin@example.com"
        yield 'escaped quote in a quoted value' => [['CN=jdoe",emailAddress=admin@example.com', 'O=Ex'], 'CN="jdoe\\",emailAddress=admin@example.com",O=Ex'];
        yield 'legacy, quotes are literal' => [['O=Ex', 'CN="jdoe"', 'emailAddress=jdoe@example.com'], '/O=Ex/CN="jdoe"/emailAddress=jdoe@example.com'];
    }

    private function createRequest(array $server)
    {
        return new Request([], [], [], [], [], $server);
    }
}
