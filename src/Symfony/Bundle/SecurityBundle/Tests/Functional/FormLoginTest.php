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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Clock\ClockInterface;
use Symfony\Component\RateLimiter\Event\RateLimitExceededEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationMethod;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class FormLoginTest extends AbstractWebTestCase
{
    #[DataProvider('provideClientOptions')]
    public function testFormLogin(array $options)
    {
        $client = $this->createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/profile');

        $text = $client->followRedirect()->text(null, true);
        $this->assertStringContainsString('Hello johannes!', $text);
        $this->assertStringContainsString('You\'re browsing to path "/profile".', $text);
    }

    public function testAuthenticationTimeIsRecordedAndSurvivesTheSession()
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'base_config.yml']);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);
        $client->followRedirect();

        // a second request, so the asserted token is the one restored from the session
        $client->request('GET', '/profile');

        $token = static::getContainer()->get('security.token_storage')->getToken();

        $this->assertArrayHasKey(AuthenticationMethod::PASSWORD, $token->getAuthenticationProofs());
        $this->assertGreaterThanOrEqual(time() - 60, $token->getAuthenticationProofs()[AuthenticationMethod::PASSWORD]);
        $this->assertTrue(static::getContainer()->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_RECENTLY'));

        // the optional clock must actually be injected, otherwise the strategy silently
        // falls back to time() and the wiring could rot unnoticed
        $trustResolver = static::getContainer()->get('security.authentication.trust_resolver');
        $this->assertInstanceOf(ClockInterface::class, (new \ReflectionProperty($trustResolver, 'clock'))->getValue($trustResolver));
    }

    public function testAnOutdatedAuthenticationStartsAReAuthenticationOnTheLoginForm()
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 're_authentication.yml']);

        // a session holding no authentication proof: the user is logged in, but nothing says
        // when they last proved their credentials, which is what an expired one amounts to
        $client->loginUser(new InMemoryUser('johannes', 'test', ['ROLE_USER']), 'default');

        $client->request('GET', '/protected_resource');

        $this->assertRedirect($client->getResponse(), '/login');

        $session = $client->getRequest()->getSession();
        $this->assertSame(AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY, $session->get(SecurityRequestAttributes::RE_AUTHENTICATION_ATTRIBUTE));
        $this->assertSame('http://localhost/protected_resource', $session->get('_security.default.target_path'));

        // the form only has the password left to ask for
        $form = $client->followRedirect()->selectButton('login')->form();
        $this->assertSame('johannes', $form['_username']->getValue());
    }

    public function testAReAuthenticationSendsTheUserBackToTheDeniedResource()
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 're_authentication.yml']);
        $client->loginUser(new InMemoryUser('johannes', 'test', ['ROLE_USER']), 'default');

        $client->request('GET', '/protected_resource');
        $form = $client->followRedirect()->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/protected_resource');

        $client->followRedirect();
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertFalse($client->getRequest()->getSession()->has(SecurityRequestAttributes::RE_AUTHENTICATION_ATTRIBUTE));
    }

    public function testTheTargetPathOfTheLoginFormWinsOverTheDeniedResource()
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 're_authentication.yml']);
        $client->loginUser(new InMemoryUser('johannes', 'test', ['ROLE_USER']), 'default');

        $client->request('GET', '/protected_resource');
        $form = $client->followRedirect()->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $form['_target_path'] = '/profile';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/profile');
    }

    #[DataProvider('provideClientOptions')]
    public function testFormLogout(array $options)
    {
        $client = $this->createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/profile');

        $crawler = $client->followRedirect();
        $text = $crawler->text(null, true);

        $this->assertStringContainsString('Hello johannes!', $text);
        $this->assertStringContainsString('You\'re browsing to path "/profile".', $text);

        $logoutLinks = $crawler->selectLink('Log out')->links();
        $this->assertCount(6, $logoutLinks);
        $this->assertSame($logoutLinks[0]->getUri(), $logoutLinks[1]->getUri());
        $this->assertSame($logoutLinks[2]->getUri(), $logoutLinks[3]->getUri());
        $this->assertSame($logoutLinks[4]->getUri(), $logoutLinks[5]->getUri());

        $this->assertNotSame($logoutLinks[0]->getUri(), $logoutLinks[2]->getUri());
        $this->assertNotSame($logoutLinks[1]->getUri(), $logoutLinks[3]->getUri());

        $this->assertSame($logoutLinks[0]->getUri(), $logoutLinks[4]->getUri());
        $this->assertSame($logoutLinks[1]->getUri(), $logoutLinks[5]->getUri());
    }

    #[DataProvider('provideClientOptions')]
    public function testFormLoginWithCustomTargetPath(array $options)
    {
        $client = $this->createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $form['_target_path'] = '/foo';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/foo');

        $text = $client->followRedirect()->text(null, true);
        $this->assertStringContainsString('Hello johannes!', $text);
        $this->assertStringContainsString('You\'re browsing to path "/foo".', $text);
    }

    #[DataProvider('provideClientOptions')]
    public function testFormLoginRedirectsToProtectedResourceAfterLogin(array $options)
    {
        $client = $this->createClient($options);

        $client->request('GET', '/protected_resource');
        $this->assertRedirect($client->getResponse(), '/login');

        $form = $client->followRedirect()->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);
        $this->assertRedirect($client->getResponse(), '/protected_resource');

        $text = $client->followRedirect()->text(null, true);
        $this->assertStringContainsString('Hello johannes!', $text);
        $this->assertStringContainsString('You\'re browsing to path "/protected_resource".', $text);
    }

    #[Group('time-sensitive')]
    public function testLoginThrottling()
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'login_throttling.yml']);

        $attempts = [
            ['johannes', 'wrong'],
            ['johannes', 'also_wrong'],
            ['wrong', 'wrong'],
            ['johannes', 'wrong_again'],
        ];
        foreach ($attempts as $i => $attempt) {
            $form = $client->request('GET', '/login')->selectButton('login')->form();
            $form['_username'] = $attempt[0];
            $form['_password'] = $attempt[1];
            $client->submit($form);

            $text = $client->followRedirect()->text(null, true);
            switch ($i) {
                case 0: // First attempt : Invalid credentials (OK)
                    $this->assertStringContainsString('Invalid credentials', $text, 'Invalid response on 1st attempt');

                    break;
                case 1: // Second attempt : login throttling !
                    $this->assertStringContainsString('Too many failed login attempts, please try again', $text, 'Invalid response on 2nd attempt');

                    break;
                case 2: // Third attempt with unexisting username
                    $this->assertStringContainsString('Invalid credentials.', $text, 'Invalid response on 3rd attempt');

                    break;
                case 3: // Fourth attempt : still login throttling !
                    $this->assertStringContainsString('Too many failed login attempts, please try again', $text, 'Invalid response on 4th attempt');

                    break;
            }
        }
    }

    #[Group('time-sensitive')]
    public function testLoginThrottlingDispatchesRateLimitExceededEvent()
    {
        if (!class_exists(RateLimitExceededEvent::class)) {
            $this->markTestSkipped('The installed "symfony/rate-limiter" does not provide RateLimitExceededEvent.');
        }

        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'login_throttling.yml']);

        foreach ([['johannes', 'wrong'], ['johannes', 'also_wrong']] as $attempt) {
            $form = $client->request('GET', '/login')->selectButton('login')->form();
            $form['_username'] = $attempt[0];
            $form['_password'] = $attempt[1];
            $client->submit($form);
        }

        $events = $client->getContainer()->get('app.rate_limit_exceeded_collector')->events;

        $this->assertCount(1, $events);
        $this->assertSame('security.login_throttling.default.limiter', $events[0]->getLimiterName());
        $this->assertNull($events[0]->getKey());
        $this->assertFalse($events[0]->getRateLimit()->isAccepted());
    }

    public static function provideClientOptions(): iterable
    {
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'base_config.yml']];
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'routes_as_path.yml']];
    }
}
