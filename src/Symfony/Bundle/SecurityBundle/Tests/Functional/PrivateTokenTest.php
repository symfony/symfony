<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\RequiresPrivateToken;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class PrivateTokenTest extends AbstractWebTestCase
{
    // the private key of the "token_keys" option of the test configuration, which stands for the one an issuer signs its tokens with
    private const ISSUER_PRIVATE_KEY = <<<KEY
        -----BEGIN PRIVATE KEY-----
        MIIE8AIBADBBBgkqhkiG9w0BAQowNKAPMA0GCWCGSAFlAwQCAgUAoRwwGgYJKoZI
        hvcNAQEIMA0GCWCGSAFlAwQCAgUAogMCATAEggSmMIIEogIBAAKCAQEAunFppTw/
        x8bJYWWkMM4tOge5DG1tqzTQdU9UCsZtXn7/MoTrP4w2HE8dIk1hygCSsa0/YNXi
        4EcdMjFa7x5biUUfp+C+N6JL7Ia1p3I+a3WQgSoPaizNeT+5CtuuU2ZgwnZFdpW2
        OnoanYfBpzGGrzERsJmlbOtAtjB7RRADqcCkLQA3XNfsYZJ/Ntcq7wS9RU9fo5pz
        +2rJgow9Vuhq39pLJBn7OHjygqFdPS1kiFmM2t9syUB/XwxDA4cLSY3rAGkJeKLm
        kq3pujMjnkxdjXMiNahcuXPXxg/VxoXrwEm9SWqEyNMnbjp11j9rK5BfHpTWk/fm
        9Z/uybgUAxKqOQIDAQABAoIBAAKxrtgfZcCKQ9Cvek7mO8sR33+peSM3MSfwzIMX
        WO87oI5nU0royMNh9UbQnXAgz/3XRgotLQkWnfpisjCwXJxALnPrkHQXWUeCrs9L
        nFFLMppbNDvRh+qPnCnnAL7dxNoqgVh0U1zG9Ol/07GJyqtvZxUJM1W+lIHNc4qM
        77CdPKd3xIp6XyGHbG6hTr9w/GP3YHIlbaWJzBbd9y5ACi4/OWwVxMsTS6M79M3b
        RZ8Pyx245+4CtbnW+oaRdFq9512GwpCopi9VOt9LPrhqaATDcm4qKK5Yc4RB+uaK
        vXwy7pSkqMErpKRLPXL+ZbCclyQW1lpw/uEaJaGV+FOlhx0CgYEA9IiubYGbS0gp
        fx4FkLtu+cwWp3y1u6h0HPbYNgkQAEVF/8XKGriGwo7mU8jvaM5nDP9ZOcb+N45R
        UaZvKxU8R+oaCXxfn/4gwpblrF7WUFry2GRzB6ddtGs1+1NiOPqI0PuhI2SsJI7J
        cjQNuHx84ZjIRsAj0HXMsANzZMq/8QcCgYEAwy9sjGz3h/xV0VDYzIzdch4vJX4+
        QR5ma3zSBZ+x6dm59ZdMqnn5kFDVP5H1ItVolZ1XzAlihU0pIzn0wRBLqTA548IV
        ofSs/vziaY2NLqwFxxVxHcmaXY3O/1fu+ifFCZ0ys1iw7c3m8kT5A8eZo7m1Ma2e
        NL/iudh876lb+r8CgYBsvUcLjSHYhTtmupvw81hzHm1Ih21tCIixxJb62GbUJ/nr
        tjGcVkgexlroESddLMOohPhReArd+5xvuZofx26p8BEp/LOtOt2oil+Rmg6lZvws
        e+anFabWrwIvQ7Ie8lHa1LqgOYlPLGuqLDmZu0nxK/aLd6+1aFzgPHiY7owSCwKB
        gBewX8pDR1+Xvoqi900MI6xAAZTZV5DrsZ6GDa9lDGDHbgxiiol+OE3NobQPcOMD
        Z6gQ6qD22qA3FzERTaVI5wXntn7wBVQcsApagtgwt9vcQO9CqPHmj/eeyGqxL23o
        +b8K9yZRM1gJT2hD+rz8sqbuWRFocvUP/OJ1ZeRod6NdAoGANt/H3ZkixvN1qdn2
        8WTA2YgdY+eAQS53nglL8FT94iNEzM2+IqMb5ElWFeFsVEZit+niTAXmms8JROsO
        Z3+GMbA1n/iiNVjjUIEvzGTt7DpKT+UpzcO0Lvoe7UuUE3Qw0J7BCCFFnRBL/8bi
        ayy1xO4JrXSP3qfIhSMZsPesH1Y=
        -----END PRIVATE KEY-----
        KEY;

    public function testARequestCarryingNoTokenIsChallenged()
    {
        $client = $this->createClient(['test_case' => 'PrivateToken', 'root_config' => 'config.yml']);
        $client->request('GET', '/contact');

        $response = $client->getResponse();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringStartsWith('PrivateToken challenge="', $response->headers->get('WWW-Authenticate'));
        $this->assertStringContainsString('max-age="3600"', $response->headers->get('WWW-Authenticate'));
    }

    public function testARequestCarryingATokenReachesTheController()
    {
        $client = $this->createClient(['test_case' => 'PrivateToken', 'root_config' => 'config.yml']);

        $client->request('GET', '/contact', [], [], ['HTTP_AUTHORIZATION' => $this->redeem($client, '/contact')]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('contact form', $client->getResponse()->getContent());
    }

    public function testATokenIsSpentOnce()
    {
        $client = $this->createClient(['test_case' => 'PrivateToken', 'root_config' => 'config.yml']);
        $authorization = $this->redeem($client, '/contact');

        $client->request('GET', '/contact', [], [], ['HTTP_AUTHORIZATION' => $authorization]);
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('GET', '/contact', [], [], ['HTTP_AUTHORIZATION' => $authorization]);
        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    public function testAnOptionalTokenLetsTheControllerAnswerTheChallenge()
    {
        $client = $this->createClient(['test_case' => 'PrivateToken', 'root_config' => 'config.yml']);
        $client->request('GET', '/optional');

        $response = $client->getResponse();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('captcha', $response->getContent());
        $this->assertStringStartsWith('PrivateToken challenge="', $response->headers->get('WWW-Authenticate'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    public function testAnOptionalTokenThatIsPresentedIsAccepted()
    {
        $client = $this->createClient(['test_case' => 'PrivateToken', 'root_config' => 'config.yml']);

        $client->request('GET', '/optional', [], [], ['HTTP_AUTHORIZATION' => $this->redeem($client, '/optional')]);

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('no captcha', $client->getResponse()->getContent());
    }

    public function testASessionThatPresentedATokenIsAcceptedWithoutOne()
    {
        $client = $this->createClient(['test_case' => 'PrivateToken', 'root_config' => 'config.yml']);

        $client->request('GET', '/remembered', [], [], ['HTTP_AUTHORIZATION' => $this->redeem($client, '/remembered')]);
        $this->assertSame(200, $client->getResponse()->getStatusCode());

        $client->request('GET', '/remembered');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame('remembered', $client->getResponse()->getContent());

        // what one configuration remembers of a session does not accept it for another
        $client->request('GET', '/remembered-other');
        $this->assertSame(401, $client->getResponse()->getStatusCode());

        $client->request('GET', '/contact');
        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }

    private function redeem(KernelBrowser $client, string $path): string
    {
        $client->request('GET', $path);
        $header = $client->getResponse()->headers->get('WWW-Authenticate');

        $this->assertSame(1, preg_match('/challenge="([^"]+)", token-key="([^"]+)"/', $header, $parameters), $header);

        $challenge = base64_decode(strtr($parameters[1], '-_', '+/'), true);
        $tokenKey = base64_decode(strtr($parameters[2], '-_', '+/'), true);

        $input = pack('n', 0x0002).random_bytes(32).hash('sha256', $challenge, true).hash('sha256', $tokenKey, true);

        $this->assertTrue(openssl_sign($input, $authenticator, self::ISSUER_PRIVATE_KEY, \OPENSSL_ALGO_SHA384));

        return \sprintf('PrivateToken token="%s"', strtr(base64_encode($input.$authenticator), '+/', '-_'));
    }
}

class PrivateTokenController
{
    #[RequiresPrivateToken]
    public function contact(): Response
    {
        return new Response('contact form');
    }

    #[RequiresPrivateToken('remembered')]
    public function remembered(): Response
    {
        return new Response('remembered');
    }

    #[RequiresPrivateToken('remembered_other')]
    public function rememberedOther(): Response
    {
        return new Response('remembered elsewhere');
    }

    #[RequiresPrivateToken(optional: true)]
    public function optional(Request $request): Response
    {
        return new Response($request->attributes->get(SecurityRequestAttributes::PRIVATE_TOKEN_VALID) ? 'no captcha' : 'captcha');
    }
}
