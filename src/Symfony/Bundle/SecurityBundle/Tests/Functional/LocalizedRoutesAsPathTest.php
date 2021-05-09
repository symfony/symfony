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

class LocalizedRoutesAsPathTest extends AbstractWebTestCase
{
    /**
     * @dataProvider getLocalesAndClientConfig
     */
    public function testLoginLogoutProcedure($locale, array $options)
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin'] + $options);

        $crawler = $client->request('GET', '/'.$locale.'/login');
        $form = $crawler->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/'.$locale.'/profile');
        $this->assertEquals('Profile', $client->followRedirect()->text());

        $client->request('GET', '/'.$locale.'/logout');
        $this->assertRedirect($client->getResponse(), '/'.$locale.'/');
        $this->assertEquals('Homepage', $client->followRedirect()->text());
    }

    /**
     * @group issue-32995
     * @dataProvider getLocalesAndClientConfig
     */
    public function testLoginFailureWithLocalizedFailurePath($locale, array $options)
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'localized_form_failure_handler.yml'] + $options);

        $crawler = $client->request('GET', '/'.$locale.'/login');
        $form = $crawler->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'foobar';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/'.$locale.'/login');
    }

    /**
     * @dataProvider getLocalesAndClientConfig
     */
    public function testAccessRestrictedResource($locale, array $options)
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin'] + $options);

        $client->request('GET', '/'.$locale.'/secure/');
        $this->assertRedirect($client->getResponse(), '/'.$locale.'/login');
    }

    /**
     * @dataProvider getLocalesAndClientConfig
     */
    public function testAccessRestrictedResourceWithForward($locale, array $options)
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'localized_routes_with_forward.yml'] + $options);

        $crawler = $client->request('GET', '/'.$locale.'/secure/');
        $this->assertCount(1, $crawler->selectButton('login'), (string) $client->getResponse());
    }

    /**
     * @group legacy
     * @dataProvider getLegacyLocalesAndClientConfig
     */
    public function testLegacyLoginLogoutProcedure($locale, array $options)
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin'] + $options);

        $crawler = $client->request('GET', '/'.$locale.'/login');
        $form = $crawler->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/'.$locale.'/profile');
        $this->assertEquals('Profile', $client->followRedirect()->text());

        $client->request('GET', '/'.$locale.'/logout');
        $this->assertRedirect($client->getResponse(), '/'.$locale.'/');
        $this->assertEquals('Homepage', $client->followRedirect()->text());
    }

    /**
     * @group issue-32995
     * @group legacy
     * @dataProvider getLegacyLocalesAndClientConfig
     */
    public function testLegacyLoginFailureWithLocalizedFailurePath($locale, array $options)
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'legacy_localized_form_failure_handler.yml'] + $options);

        $crawler = $client->request('GET', '/'.$locale.'/login');
        $form = $crawler->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'foobar';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/'.$locale.'/login');
    }

    /**
     * @group legacy
     * @dataProvider getLegacyLocalesAndClientConfig
     */
    public function testLegacyAccessRestrictedResource($locale, array $options)
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin'] + $options);

        $client->request('GET', '/'.$locale.'/secure/');
        $this->assertRedirect($client->getResponse(), '/'.$locale.'/login');
    }

    /**
     * @group legacy
     * @dataProvider getLegacyLocalesAndClientConfig
     */
    public function testLegacyAccessRestrictedResourceWithForward($locale, array $options)
    {
        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'legacy_localized_routes_with_forward.yml'] + $options);

        $crawler = $client->request('GET', '/'.$locale.'/secure/');
        $this->assertCount(1, $crawler->selectButton('login'), (string) $client->getResponse());
    }

    public function getLocalesAndClientConfig()
    {
        yield ['en', ['root_config' => 'localized_routes.yml']];
        yield ['de', ['root_config' => 'localized_routes.yml']];
    }

    public function getLegacyLocalesAndClientConfig()
    {
        yield ['en', ['root_config' => 'legacy_localized_routes.yml']];
        yield ['de', ['root_config' => 'legacy_localized_routes.yml']];
    }
}
