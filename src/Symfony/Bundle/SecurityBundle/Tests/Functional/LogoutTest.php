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
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LogoutTest extends AbstractWebTestCase
{
    public function testCsrfTokensAreClearedOnLogout()
    {
        $client = $this->createClient(['test_case' => 'LogoutWithoutSessionInvalidation', 'root_config' => 'config.yml']);
        $client->disableReboot();

        $client->request('POST', '/login', [
            '_username' => 'johannes',
            '_password' => 'test',
        ]);

        $this->callInRequestContext($client, static function () {
            static::getContainer()->get('security.csrf.token_storage')->setToken('foo', 'bar');
        });

        $client->request('GET', '/logout');

        $this->callInRequestContext($client, function () {
            $this->assertFalse(static::getContainer()->get('security.csrf.token_storage')->hasToken('foo'));
        });
    }

    public function testAccessControlDoesNotApplyOnLogout()
    {
        $client = $this->createClient(['test_case' => 'Logout', 'root_config' => 'config_access.yml']);

        $client->request('POST', '/login', ['_username' => 'johannes', '_password' => 'test']);
        $client->request('GET', '/logout');

        $this->assertRedirect($client->getResponse(), '/');
    }

    public function testCookieClearingOnLogout()
    {
        $client = $this->createClient(['test_case' => 'Logout', 'root_config' => 'config_cookie_clearing.yml']);

        $cookieJar = $client->getCookieJar();
        $cookieJar->set(new Cookie('flavor', 'chocolate', strtotime('+1 day'), null, 'somedomain'));

        $client->request('POST', '/login', ['_username' => 'johannes', '_password' => 'test']);
        $client->request('GET', '/logout');

        $this->assertRedirect($client->getResponse(), '/');
        $this->assertNull($cookieJar->get('flavor'));
    }

    public function testEnabledCsrf()
    {
        $client = $this->createClient(['test_case' => 'Logout', 'root_config' => 'config_csrf_enabled.yml']);

        $cookieJar = $client->getCookieJar();
        $cookieJar->set(new Cookie('flavor', 'chocolate', strtotime('+1 day'), null, 'somedomain'));

        $client->request('POST', '/login', ['_username' => 'johannes', '_password' => 'test']);
        $client->request('GET', '/logout');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testTheCsrfTokenManagerOfTheFirewallMintsTheLogoutToken()
    {
        $client = $this->createClient(['test_case' => 'Logout', 'root_config' => 'config_csrf_custom_manager.yml']);

        $client->request('POST', '/login', ['_username' => 'johannes', '_password' => 'test']);

        // this is what csrf_token('logout') does in a template
        $csrfToken = static::getContainer()->get('security.csrf.token_manager')->getToken('logout')->getValue();

        $this->assertSame(FixedCsrfTokenManager::VALUE, $csrfToken);

        $client->request('GET', '/logout?_csrf_token='.$csrfToken);

        $this->assertRedirect($client->getResponse(), '/');
    }

    public function testTheCsrfTokenManagerOfTheFirewallWinsOverStatelessTokenIds()
    {
        $client = $this->createClient(['test_case' => 'Logout', 'root_config' => 'config_csrf_custom_manager_stateless.yml']);

        $client->request('POST', '/login', ['_username' => 'johannes', '_password' => 'test']);

        $csrfToken = static::getContainer()->get('security.csrf.token_manager')->getToken('logout')->getValue();

        $this->assertSame(FixedCsrfTokenManager::VALUE, $csrfToken);

        $client->request('GET', '/logout?_csrf_token='.$csrfToken);

        $this->assertRedirect($client->getResponse(), '/');
    }

    public function testTheLogoutFormHelperBuildsAFormTheListenerAccepts()
    {
        $client = $this->createAuthenticatedClient();

        $crawler = $client->request('GET', '/logout-form');
        $client->submit($crawler->selectButton('logout')->form());

        $this->assertRedirect($client->getResponse(), '/');
    }

    public function testLoggingOutWithoutTheTokenTheFormCarriesIsDenied()
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/logout');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testTheLogoutPathCannotBeFollowedAsALinkWhenTheRouteIsPostOnly()
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/logout');

        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    private function createAuthenticatedClient(): KernelBrowser
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'logout_csrf.yml']);
        $client->followRedirects(true);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);
        $client->followRedirects(false);

        return $client;
    }

    private function callInRequestContext(KernelBrowser $client, callable $callable): void
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $wrappedCallable = static function (RequestEvent $event) use (&$callable) {
            $callable();
            $event->setResponse(new Response(''));
            $event->stopPropagation();
        };

        $eventDispatcher->addListener(KernelEvents::REQUEST, $wrappedCallable);
        try {
            $client->request('GET', '/not-existent');
        } finally {
            $eventDispatcher->removeListener(KernelEvents::REQUEST, $wrappedCallable);
        }
    }
}
