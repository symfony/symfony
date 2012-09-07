<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Http\Firewall;

use Symfony\Component\Security\Http\Firewall\DigestData;

class DigestDataTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        class_exists('Symfony\Component\Security\Http\Firewall\DigestAuthenticationListener', true);
    }

    public function testGetResponse()
    {
        $digestAuth = new DigestData(
            'username="user", realm="Welcome, robot!", ' .
            'nonce="MTM0NzMyMTgyMy42NzkzOmRlZjM4NmIzOGNjMjE0OWJiNDU0MDAxNzJmYmM1MmZl", ' .
            'uri="/path/info?p1=5&p2=5", cnonce="MDIwODkz", nc=00000001, qop="auth", ' .
            'response="b52938fc9e6d7c01be7702ece9031b42"'
        );

        $this->assertEquals('b52938fc9e6d7c01be7702ece9031b42', $digestAuth->getResponse());
    }

    public function testGetUsername()
    {
        $digestAuth = new DigestData(
            'username="user", realm="Welcome, robot!", ' .
            'nonce="MTM0NzMyMTgyMy42NzkzOmRlZjM4NmIzOGNjMjE0OWJiNDU0MDAxNzJmYmM1MmZl", ' .
            'uri="/path/info?p1=5&p2=5", cnonce="MDIwODkz", nc=00000001, qop="auth", ' .
            'response="b52938fc9e6d7c01be7702ece9031b42"'
        );

        $this->assertEquals('user', $digestAuth->getUsername());
    }

    public function testValidateAndDecode()
    {
        $time = microtime(true);
        $key = 'ThisIsAKey';
        $nonce = base64_encode($time . ':' . md5($time . ':' . $key));

        $digestAuth = new DigestData(
            'username="user", realm="Welcome, robot!", nonce="' . $nonce . '", ' .
            'uri="/path/info?p1=5&p2=5", cnonce="MDIwODkz", nc=00000001, qop="auth", ' .
            'response="b52938fc9e6d7c01be7702ece9031b42"'
        );

        try {
            $digestAuth->validateAndDecode($key, 'Welcome, robot!');
        } catch (\Exception $e) {
            $this->fail(sprintf('testValidateAndDecode fail with message: %s', $e->getMessage()));
        }
    }

    public function testCalculateServerDigest()
    {
        $username = 'user';
        $realm = 'Welcome, robot!';
        $password = 'pass,word=password';
        $time = microtime(true);
        $key = 'ThisIsAKey';
        $nonce = base64_encode($time . ':' . md5($time . ':' . $key));
        $nc = '00000001';
        $cnonce = 'MDIwODkz';
        $qop = 'auth';
        $method = 'GET';
        $uri = '/path/info?p1=5&p2=5';

        $response = md5(
            md5($username . ':' . $realm . ':' . $password) .
            ':' . $nonce . ':' . $nc . ':' . $cnonce . ':' . $qop . ':' . md5($method . ':' . $uri)
        );

        $digest = sprintf('username="%s", realm="%s", nonce="%s", uri="%s", cnonce="%s", nc="%s", qop="%s", response="%s"',
            $username, $realm, $nonce, $uri, $cnonce, $nc, $qop, $response
        );

        $digestAuth = new DigestData($digest);

        $this->assertEquals($digestAuth->getResponse(), $digestAuth->calculateServerDigest($password, $method));
    }

    public function testIsNonceExpired()
    {
        $time = microtime(true) + 10;
        $key = 'ThisIsAKey';
        $nonce = base64_encode($time . ':' . md5($time . ':' . $key));

        $digestAuth = new DigestData(
            'username="user", realm="Welcome, robot!", nonce="' . $nonce . '", ' .
            'uri="/path/info?p1=5&p2=5", cnonce="MDIwODkz", nc=00000001, qop="auth", ' .
            'response="b52938fc9e6d7c01be7702ece9031b42"'
        );

        $digestAuth->validateAndDecode($key, 'Welcome, robot!');

        $this->assertFalse($digestAuth->isNonceExpired());
    }
}