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

class UnicodeRoutesTest extends WebTestCase
{
    /**
     * @var string Login username
     */
    protected $username = 'johannes';

    /**
     * @var string Login password
     */
    protected $password = 'test';

    /**
     * @var array Routes helper
     */
    protected $routes   = array(
        'homepage' => '/домашняя_страница',
        'profile'  => '/профайл',
        'login'    => '/вход',
        'logout'   => '/выход',
    );

    public function testLoginLogoutProcedure()
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => 'unicode_routes_in_firewall.yml'));
        $client->insulate();

        $crawler = $client->request('GET', $this->getRoute('login'));
        $form = $crawler->selectButton('login')->form();
        $form['_username'] = $this->username;
        $form['_password'] = $this->password;
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), $this->getRoute('profile'));
        $this->assertEquals('Profile', $client->followRedirect()->text());

        $client->request('GET', $this->getRoute('logout'));
        $this->assertRedirect($client->getResponse(), $this->getRoute('homepage'));
        $this->assertEquals('Homepage', $client->followRedirect()->text());
    }

    public function getRoute($name)
    {
        if (!isset($this->routes[$name])) {
            throw new \InvalidArgumentException(sprintf('No route defined with name: %s', $name));
        }

        return $this->routes[$name];
    }

    protected function setUp()
    {
        parent::setUp();

        $this->deleteTmpDir('StandardFormLogin');
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->deleteTmpDir('StandardFormLogin');
    }
}
