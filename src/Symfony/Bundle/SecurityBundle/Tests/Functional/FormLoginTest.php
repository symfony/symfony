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

class FormLoginTest extends AbstractWebTestCase
{
    /**
     * @dataProvider provideClientOptions
     */
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

    /**
     * @dataProvider provideClientOptions
     */
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

    /**
     * @dataProvider provideClientOptions
     */
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

    /**
     * @dataProvider provideClientOptions
     */
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

    /**
     * @group time-sensitive
     */
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

    public static function provideClientOptions()
    {
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'base_config.yml']];
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'routes_as_path.yml']];
    }
}
