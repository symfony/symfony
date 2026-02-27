<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Translation\LocaleSwitcher;

class AppVariableTest extends TestCase
{
    protected AppVariable $appVariable;

    protected function setUp(): void
    {
        $this->appVariable = new AppVariable();
    }

    #[DataProvider('debugDataProvider')]
    public function testDebug($debugFlag)
    {
        $this->appVariable->setDebug($debugFlag);

        $this->assertEquals($debugFlag, $this->appVariable->getDebug());
    }

    public static function debugDataProvider(): array
    {
        return [
            'debug on' => [true],
            'debug off' => [false],
        ];
    }

    public function testEnvironment()
    {
        $this->appVariable->setEnvironment('dev');

        $this->assertEquals('dev', $this->appVariable->getEnvironment());
    }

    #[RunInSeparateProcess]
    public function testGetSession()
    {
        $session = new Session();
        $request = new Request();
        $request->setSession($session);

        $this->setRequestStack($request);

        $this->assertEquals($session, $this->appVariable->getSession());
    }

    public function testGetSessionWithNoRequest()
    {
        $this->setRequestStack(null);

        $this->assertNull($this->appVariable->getSession());
    }

    public function testGetRequest()
    {
        $this->setRequestStack($request = new Request());

        $this->assertEquals($request, $this->appVariable->getRequest());
    }

    public function testGetToken()
    {
        $tokenStorage = new TokenStorage();
        $this->appVariable->setTokenStorage($tokenStorage);

        $token = new NullToken();
        $tokenStorage->setToken($token);

        $this->assertEquals($token, $this->appVariable->getToken());
    }

    public function testGetUser()
    {
        $this->setTokenStorage($user = new InMemoryUser('john', 'password'));

        $this->assertEquals($user, $this->appVariable->getUser());
    }

    public function testGetLocale()
    {
        $this->appVariable->setLocaleSwitcher(new LocaleSwitcher('fr', []));

        self::assertEquals('fr', $this->appVariable->getLocale());
    }

    public function testGetEnabledLocales()
    {
        $this->appVariable->setEnabledLocales(['en', 'fr']);

        self::assertSame(['en', 'fr'], $this->appVariable->getEnabled_locales());
    }

    public function testGetTokenWithNoToken()
    {
        $this->appVariable->setTokenStorage(new TokenStorage());

        $this->assertNull($this->appVariable->getToken());
    }

    public function testGetUserWithNoToken()
    {
        $this->appVariable->setTokenStorage(new TokenStorage());

        $this->assertNull($this->appVariable->getUser());
    }

    public function testEnvironmentNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getEnvironment();
    }

    public function testDebugNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getDebug();
    }

    public function testGetTokenWithTokenStorageNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getToken();
    }

    public function testGetUserWithTokenStorageNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getUser();
    }

    public function testGetRequestWithRequestStackNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getRequest();
    }

    public function testGetSessionWithRequestStackNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getSession();
    }

    public function testGetLocaleWithLocaleSwitcherNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "app.locale" variable is not available.');
        $this->appVariable->getLocale();
    }

    public function testGetEnabledLocalesWithEnabledLocalesNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "app.enabled_locales" variable is not available.');
        $this->appVariable->getEnabled_locales();
    }

    public function testGetFlashesWithNoRequest()
    {
        $this->setRequestStack(null);

        $this->assertEquals([], $this->appVariable->getFlashes());
    }

    #[RunInSeparateProcess]
    public function testGetFlashesWithNoSessionStarted()
    {
        $flashMessages = $this->setFlashMessages(false);
        $this->assertEquals($flashMessages, $this->appVariable->getFlashes());
    }

    #[RunInSeparateProcess]
    public function testGetFlashes()
    {
        $flashMessages = $this->setFlashMessages();
        $this->assertEquals($flashMessages, $this->appVariable->getFlashes(null));

        $flashMessages = $this->setFlashMessages();
        $this->assertEquals($flashMessages, $this->appVariable->getFlashes(''));

        $flashMessages = $this->setFlashMessages();
        $this->assertEquals($flashMessages, $this->appVariable->getFlashes([]));

        $this->setFlashMessages();
        $this->assertEquals([], $this->appVariable->getFlashes('this-does-not-exist'));

        $this->setFlashMessages();
        $this->assertEquals(
            ['this-does-not-exist' => []],
            $this->appVariable->getFlashes(['this-does-not-exist'])
        );

        $flashMessages = $this->setFlashMessages();
        $this->assertEquals($flashMessages['notice'], $this->appVariable->getFlashes('notice'));

        $flashMessages = $this->setFlashMessages();
        $this->assertEquals(
            ['notice' => $flashMessages['notice']],
            $this->appVariable->getFlashes(['notice'])
        );

        $flashMessages = $this->setFlashMessages();
        $this->assertEquals(
            ['notice' => $flashMessages['notice'], 'this-does-not-exist' => []],
            $this->appVariable->getFlashes(['notice', 'this-does-not-exist'])
        );

        $flashMessages = $this->setFlashMessages();
        $this->assertEquals(
            ['notice' => $flashMessages['notice'], 'error' => $flashMessages['error']],
            $this->appVariable->getFlashes(['notice', 'error'])
        );

        $this->assertEquals(
            ['warning' => $flashMessages['warning']],
            $this->appVariable->getFlashes(['warning']),
            'After getting some flash types (e.g. "notice" and "error"), the rest of flash messages must remain (e.g. "warning").'
        );

        $this->assertEquals(
            ['this-does-not-exist' => []],
            $this->appVariable->getFlashes(['this-does-not-exist'])
        );
    }

    public function testGetCurrentRoute()
    {
        $this->setRequestStack(new Request(attributes: ['_route' => 'some_route']));

        $this->assertSame('some_route', $this->appVariable->getCurrent_route());
    }

    public function testGetCurrentRouteWithRequestStackNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getCurrent_route();
    }

    public function testGetCurrentRouteParameters()
    {
        $routeParams = ['some_param' => true];
        $this->setRequestStack(new Request(attributes: ['_route_params' => $routeParams]));

        $this->assertSame($routeParams, $this->appVariable->getCurrent_route_parameters());
    }

    public function testGetCurrentRouteParametersWithoutAttribute()
    {
        $this->setRequestStack(new Request());

        $this->assertSame([], $this->appVariable->getCurrent_route_parameters());
    }

    public function testGetCurrentRouteParametersWithRequestStackNotSet()
    {
        $this->expectException(\RuntimeException::class);
        $this->appVariable->getCurrent_route_parameters();
    }

    protected function setRequestStack(?Request $request)
    {
        $requestStack = new RequestStack();

        if (null !== $request) {
            $requestStack->push($request);
        }

        $this->appVariable->setRequestStack($requestStack);
    }

    protected function setTokenStorage($user)
    {
        $tokenStorage = new TokenStorage();
        $this->appVariable->setTokenStorage($tokenStorage);

        $token = new UsernamePasswordToken($user, 'main');
        $tokenStorage->setToken($token);
    }

    public function testSetEnabledLocalesFiltersEmptyValues()
    {
        $this->appVariable->setEnabledLocales(['en', '', 'fr', null, 'de']);

        $enabledLocales = $this->appVariable->getEnabled_locales();

        $this->assertEquals(['en', 'fr', 'de'], array_values($enabledLocales));
    }

    private function setFlashMessages($sessionHasStarted = true)
    {
        $flashMessages = [
            'notice' => ['Notice #1 message'],
            'warning' => ['Warning #1 message'],
            'error' => ['Error #1 message', 'Error #2 message'],
        ];

        $storage = new MockArraySessionStorage();
        $storage->setSessionData([
            '_symfony_flashes' => $flashMessages,
        ]);
        $session = new Session($storage);

        if ($sessionHasStarted) {
            $session->start();
        }

        $request = new Request();
        $request->setSession($session);
        $this->setRequestStack($request);

        return $flashMessages;
    }
}
