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

use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FirewallPostAuthenticationBundle\Controller\SecureController;
use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FirewallPostAuthenticationBundle\Security\CustomAccessListener;
use Symfony\Component\HttpFoundation\Response;

class FirewallPostAuthenticationTest extends WebTestCase
{
    public function testCanAccessAuthorizedContentAsCorrectUser()
    {
        $response = $this->getResponseAsUser(CustomAccessListener::VALID_USERNAME);

        $this->assertEquals(
            SecureController::CONTENT,
            $response->getContent(),
            "Custom authorization failed"
        );
    }

    public function testCannotAccessAuthorizedContentWrongUser()
    {
        $response = $this->getResponseAsUser('jane');

        $this->assertEquals(
            Response::HTTP_FORBIDDEN,
            $response->getStatusCode(),
            "Access should be denied to incorrect user"
        );
    }

    public function testCannotAccessAuthorizedContentAsAnon()
    {
        $response = $this->getResponseAsUser(null);

        $this->assertEquals(
            Response::HTTP_UNAUTHORIZED,
            $response->getStatusCode(),
            "Access should be denied to anon"
        );
    }

    private function getResponseAsUser($username)
    {
        $client = $this->createClient(['test_case' => 'FirewallPostAuthentication']);

        $server = !$username ? [] : [
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW'   => 'password',
        ];

        $client->request('GET', '/secure', [], [], $server);

        return $client->getResponse();
    }

    public static function setUpBeforeClass()
    {
        parent::deleteTmpDir('FirewallPostAuthentication');
    }

    public static function tearDownAfterClass()
    {
        parent::deleteTmpDir('FirewallPostAuthentication');
    }
}
