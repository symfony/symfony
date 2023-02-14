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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class AbstractLoginFormAuthenticatorTest extends TestCase
{
    /**
     * @dataProvider provideSupportsData
     */
    public function testSupports(string $loginUrl, Request $request, bool $expected)
    {
        $authenticator = new ConcreteFormAuthenticator($loginUrl);
        $this->assertSame($expected, $authenticator->supports($request));
    }

    public static function provideSupportsData(): iterable
    {
        yield [
            '/login',
            Request::create('http://localhost/login', Request::METHOD_POST, [], [], [], [
                'DOCUMENT_ROOT' => '/var/www/app/public',
                'PHP_SELF' => '/index.php',
                'SCRIPT_FILENAME' => '/var/www/app/public/index.php',
                'SCRIPT_NAME' => '/index.php',
            ]),
            true,
        ];
        yield [
            '/login',
            Request::create('http://localhost/somepath', Request::METHOD_POST, [], [], [], [
                'DOCUMENT_ROOT' => '/var/www/app/public',
                'PHP_SELF' => '/index.php',
                'SCRIPT_FILENAME' => '/var/www/app/public/index.php',
                'SCRIPT_NAME' => '/index.php',
            ]),
            false,
        ];
        yield [
            '/folder/login',
            Request::create('http://localhost/folder/login', Request::METHOD_POST, [], [], [], [
                'DOCUMENT_ROOT' => '/var/www/app/public',
                'PHP_SELF' => '/folder/index.php',
                'SCRIPT_FILENAME' => '/var/www/app/public/index.php',
                'SCRIPT_NAME' => '/folder/index.php',
            ]),
            true,
        ];
        yield [
            '/folder/login',
            Request::create('http://localhost/folder/somepath', Request::METHOD_POST, [], [], [], [
                'DOCUMENT_ROOT' => '/var/www/app/public',
                'PHP_SELF' => '/folder/index.php',
                'SCRIPT_FILENAME' => '/var/www/app/public/index.php',
                'SCRIPT_NAME' => '/folder/index.php',
            ]),
            false,
        ];
        yield [
            '/index.php/login',
            Request::create('http://localhost/index.php/login', Request::METHOD_POST, [], [], [], [
                'DOCUMENT_ROOT' => '/var/www/app/public',
                'PHP_SELF' => '/index.php',
                'SCRIPT_FILENAME' => '/var/www/app/public/index.php',
                'SCRIPT_NAME' => '/index.php',
            ]),
            true,
        ];
        yield [
            '/index.php/login',
            Request::create('http://localhost/index.php/somepath', Request::METHOD_POST, [], [], [], [
                'DOCUMENT_ROOT' => '/var/www/app/public',
                'PHP_SELF' => '/index.php',
                'SCRIPT_FILENAME' => '/var/www/app/public/index.php',
                'SCRIPT_NAME' => '/index.php',
            ]),
            false,
        ];
    }
}

class ConcreteFormAuthenticator extends AbstractLoginFormAuthenticator
{
    private $loginUrl;

    public function __construct(string $loginUrl)
    {
        $this->loginUrl = $loginUrl;
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->loginUrl;
    }

    public function authenticate(Request $request): Passport
    {
        return new SelfValidatingPassport(new UserBadge('dummy'));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }
}
