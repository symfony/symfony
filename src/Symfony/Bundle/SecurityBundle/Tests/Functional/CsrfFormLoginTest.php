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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CsrfFormLoginTest extends AbstractWebTestCase
{
    /**
     * @dataProvider provideClientOptions
     */
    public function testFormLoginAndLogoutWithCsrfTokens($options)
    {
        $client = $this->createClient($options);

        $this->callInRequestContext($client, function () {
            static::getContainer()->get('security.csrf.token_storage')->setToken('foo', 'bar');
        });

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['user_login[username]'] = 'johannes';
        $form['user_login[password]'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/profile');

        $crawler = $client->followRedirect();

        $text = $crawler->text(null, true);
        $this->assertStringContainsString('Hello johannes!', $text);
        $this->assertStringContainsString('You\'re browsing to path "/profile".', $text);

        $logoutLinks = $crawler->selectLink('Log out')->links();
        $this->assertCount(2, $logoutLinks);
        $this->assertStringContainsString('_csrf_token=', $logoutLinks[0]->getUri());

        $client->click($logoutLinks[0]);

        $this->assertRedirect($client->getResponse(), '/');

        $this->callInRequestContext($client, function () {
            $this->assertFalse(static::getContainer()->get('security.csrf.token_storage')->hasToken('foo'));
        });
    }

    /**
     * @dataProvider provideClientOptions
     */
    public function testFormLoginWithInvalidCsrfToken($options)
    {
        $client = $this->createClient($options);

        $this->callInRequestContext($client, function () {
            static::getContainer()->get('security.csrf.token_storage')->setToken('foo', 'bar');
        });

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['user_login[_token]'] = '';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/login');

        $text = $client->followRedirect()->text(null, true);
        $this->assertStringContainsString('Invalid CSRF token.', $text);

        $this->callInRequestContext($client, function () {
            $this->assertTrue(static::getContainer()->get('security.csrf.token_storage')->hasToken('foo'));
        });
    }

    /**
     * @dataProvider provideClientOptions
     */
    public function testFormLoginWithCustomTargetPath($options)
    {
        $client = $this->createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['user_login[username]'] = 'johannes';
        $form['user_login[password]'] = 'test';
        $form['user_login[_target_path]'] = '/foo';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/foo');

        $text = $client->followRedirect()->text(null, true);
        $this->assertStringContainsString('Hello johannes!', $text);
        $this->assertStringContainsString('You\'re browsing to path "/foo".', $text);
    }

    /**
     * @dataProvider provideClientOptions
     */
    public function testFormLoginRedirectsToProtectedResourceAfterLogin($options)
    {
        $client = $this->createClient($options);

        $client->request('GET', '/protected-resource');
        $this->assertRedirect($client->getResponse(), '/login');

        $form = $client->followRedirect()->selectButton('login')->form();
        $form['user_login[username]'] = 'johannes';
        $form['user_login[password]'] = 'test';
        $client->submit($form);
        $this->assertRedirect($client->getResponse(), '/protected-resource');

        $text = $client->followRedirect()->text(null, true);
        $this->assertStringContainsString('Hello johannes!', $text);
        $this->assertStringContainsString('You\'re browsing to path "/protected-resource".', $text);
    }

    public static function provideClientOptions()
    {
        yield [['test_case' => 'CsrfFormLogin', 'root_config' => 'config.yml']];
        yield [['test_case' => 'CsrfFormLogin', 'root_config' => 'routes_as_path.yml']];
    }

    private function callInRequestContext(KernelBrowser $client, callable $callable): void
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = static::getContainer()->get(EventDispatcherInterface::class);
        $wrappedCallable = function (RequestEvent $event) use (&$callable) {
            $callable();
            $event->setResponse(new Response(''));
            $event->stopPropagation();
        };

        $eventDispatcher->addListener(KernelEvents::REQUEST, $wrappedCallable);
        try {
            $client->request('GET', '/'.uniqid('', true));
        } finally {
            $eventDispatcher->removeListener(KernelEvents::REQUEST, $wrappedCallable);
        }
    }
}
