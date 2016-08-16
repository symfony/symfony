<?php

namespace Symfony\Component\Security\Http\Tests\Firewall;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\EntryPoint\DigestAuthenticationEntryPoint;
use Symfony\Component\Security\Http\Firewall\DigestAuthenticationListener;

class DigestAuthenticationListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testHandleWithValidDigest()
    {
        $time = microtime(true) + 1000;
        $secret = 'ThisIsASecret';
        $nonce = base64_encode($time.':'.md5($time.':'.$secret));
        $username = 'user';
        $password = 'password';
        $realm = 'Welcome, robot!';
        $cnonce = 'MDIwODkz';
        $nc = '00000001';
        $qop = 'auth';
        $uri = '/path/info?p1=5&p2=5';

        $serverDigest = $this->calculateServerDigest($username, $realm, $password, $nc, $nonce, $cnonce, $qop, 'GET', $uri);

        $digestData =
            'username="'.$username.'", realm="'.$realm.'", nonce="'.$nonce.'", '.
            'uri="'.$uri.'", cnonce="'.$cnonce.'", nc='.$nc.', qop="'.$qop.'", '.
            'response="'.$serverDigest.'"'
        ;

        $request = new Request(array(), array(), array(), array(), array(), array('PHP_AUTH_DIGEST' => $digestData));

        $entryPoint = new DigestAuthenticationEntryPoint($realm, $secret);

        $user = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');
        $user->method('getPassword')->willReturn($password);

        $providerKey = 'TheProviderKey';

        $tokenStorage = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface');
        $tokenStorage
            ->expects($this->once())
            ->method('getToken')
            ->will($this->returnValue(null))
        ;
        $tokenStorage
            ->expects($this->once())
            ->method('setToken')
            ->with($this->equalTo(new UsernamePasswordToken($user, $password, $providerKey)))
        ;

        $userProvider = $this->getMock('Symfony\Component\Security\Core\User\UserProviderInterface');
        $userProvider->method('loadUserByUsername')->willReturn($user);

        $listener = new DigestAuthenticationListener($tokenStorage, $userProvider, $providerKey, $entryPoint);

        $event = $this->getMock('Symfony\Component\HttpKernel\Event\GetResponseEvent', array(), array(), '', false);
        $event
            ->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request))
        ;

        $listener->handle($event);
    }

    private function calculateServerDigest($username, $realm, $password, $nc, $nonce, $cnonce, $qop, $method, $uri)
    {
        $response = md5(
            md5($username.':'.$realm.':'.$password).':'.$nonce.':'.$nc.':'.$cnonce.':'.$qop.':'.md5($method.':'.$uri)
        );

        return sprintf('username="%s", realm="%s", nonce="%s", uri="%s", cnonce="%s", nc=%s, qop="%s", response="%s"',
            $username, $realm, $nonce, $uri, $cnonce, $nc, $qop, $response
        );
    }
}
