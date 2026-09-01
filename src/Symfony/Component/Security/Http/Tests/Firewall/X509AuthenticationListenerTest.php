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
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\X509Authenticator;
use Symfony\Component\Security\Http\Firewall\X509AuthenticationListener;

/**
 * @group legacy
 */
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

        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        $authenticationManager = $this->createMock(AuthenticationManagerInterface::class);

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

        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        $authenticationManager = $this->createMock(AuthenticationManagerInterface::class);

        $listener = new X509AuthenticationListener($tokenStorage, $authenticationManager, 'TheProviderKey');

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($listener, [$request]);
        $this->assertSame($result, [$emailAddress, $credentials]);
    }

    public static function dataProviderGetPreAuthenticatedDataNoUser()
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

        yield 'genuine attribute wins over one embedded in a CN' => ['cert@example.com', 'CN=jdoe\\,emailAddress=admin@target.example,emailAddress=cert@example.com'];
        yield 'genuine attribute wins over one embedded in a CN, legacy format' => ['cert@example.com', '/CN=jdoe,emailAddress=admin@target.example/emailAddress=cert@example.com'];
        yield 'slash inside a RFC 2253 value' => ['cert@example.com', 'CN=Sample/certificate DN,emailAddress=cert@example.com'];
        yield 'escaped slash in a preceding legacy value' => ['cert@example.com', '/CN=Sample\\/certificate DN/emailAddress=cert@example.com'];
        yield 'backslash then escaped slash in a preceding legacy value' => ['cert@example.com', '/CN=jdoe\\\\/emailAddress=admin@target.example/emailAddress=cert@example.com'];
        yield 'quoted preceding value' => ['cert@example.com', 'CN="Sample, certificate DN",emailAddress=cert@example.com'];
    }

    /**
     * @dataProvider provideSpoofedDns
     */
    public function testGetPreAuthenticatedDataRejectsSpoofedEmailInDn(string $dn)
    {
        $request = new Request([], [], [], [], [], ['SSL_CLIENT_S_DN' => $dn]);

        $listener = new X509AuthenticationListener($this->createMock(TokenStorageInterface::class), $this->createMock(AuthenticationManagerInterface::class), 'TheProviderKey');

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $this->expectException(BadCredentialsException::class);

        $method->invokeArgs($listener, [$request]);
    }

    public static function provideSpoofedDns()
    {
        yield 'attribute embedded in a CN' => ['CN=emailAddress=admin@target.example,O=Attacker Corp'];
        yield 'attribute embedded in an OU' => ['CN=foo,OU=emailAddress=admin@target.example,O=Attacker'];
        yield 'OID embedded in a CN' => ['CN=1.2.840.113549.1.9.1=admin@target.example,O=Attacker Corp'];
        yield 'escaped comma' => ['CN=jdoe\\,emailAddress=admin@target.example'];
        yield 'escaped comma before a legitimate attribute' => ['CN=jdoe\\,emailAddress=admin@target.example,O=Attacker'];
        yield 'hex escaped comma' => ['CN=jdoe\\2CemailAddress=admin@target.example'];
        yield 'slash in a RFC 2253 value' => ['CN=jdoe/emailAddress=admin@target.example'];
        yield 'escaped slash in a legacy value' => ['/CN=jdoe\\/emailAddress=admin@target.example'];
        yield 'backslash then escaped slash in a legacy value' => ['/CN=jdoe\\\\/emailAddress=admin@target.example'];
        yield 'comma in a legacy value' => ['/CN=jdoe,emailAddress=admin@target.example'];
        yield 'slash-separated without a leading slash' => ['CN=jdoe/emailAddress=admin@target.example,O=Attacker'];
        yield 'quoted value' => ['CN="jdoe,emailAddress=admin@target.example,x=",O=Attacker'];
        yield 'quoted value, XN_FLAG_ONELINE' => ['O = Attacker, CN = "jdoe,emailAddress=admin@target.example,x="'];
    }

    public function testGetPreAuthenticatedDataWithNonStringCredentials()
    {
        $request = new Request([], [], [], [], [], ['SSL_CLIENT_S_DN' => null]);

        $listener = new X509AuthenticationListener($this->createMock(TokenStorageInterface::class), $this->createMock(AuthenticationManagerInterface::class), 'TheProviderKey');

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $this->expectException(BadCredentialsException::class);

        $method->invokeArgs($listener, [$request]);
    }

    public function testGetPreAuthenticatedDataNoData()
    {
        $this->expectException(BadCredentialsException::class);
        $request = new Request([], [], [], [], [], []);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        $authenticationManager = $this->createMock(AuthenticationManagerInterface::class);

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
        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        $authenticationManager = $this->createMock(AuthenticationManagerInterface::class);

        $listener = new X509AuthenticationListener($tokenStorage, $authenticationManager, 'TheProviderKey', 'TheUserKey', 'TheCredentialsKey');

        $method = new \ReflectionMethod($listener, 'getPreAuthenticatedData');
        $method->setAccessible(true);

        $result = $method->invokeArgs($listener, [$request]);
        $this->assertSame($result, $userCredentials);
    }

    /**
     * @dataProvider provideDns
     */
    public function testSplitDn(array $expected, string $dn)
    {
        foreach ([X509AuthenticationListener::class, X509Authenticator::class] as $class) {
            $method = new \ReflectionMethod($class, 'splitDn');
            $method->setAccessible(true);

            $this->assertSame($expected, $method->invoke(null, $dn), $class);
        }
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
}
