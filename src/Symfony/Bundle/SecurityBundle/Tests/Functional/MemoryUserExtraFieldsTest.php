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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

class MemoryUserExtraFieldsTest extends AbstractWebTestCase
{
    public function testMemoryUserHasExtraFields()
    {
        $client = $this->createClient(['test_case' => 'MemoryUserExtraFields', 'root_config' => 'config.yml']);

        $client->request('POST', '/login', [
            '_username' => 'foo',
            '_password' => 'bar',
        ]);
        $client->request('GET', '/memory-user-extra-fields');

        $response = $client->getResponse();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('username:foo, age:77', $response->getContent());
    }
}

class MemoryUserExtraFieldsController
{
    public function __invoke(UserInterface $user): Response
    {
        $username = $user->getUsername();

        /** @var User $user */
        $age = $user->getExtraFields()['age'] ?? '';

        return new Response(
            sprintf('username:%s, age:%s', $username, $age)
        );
    }
}
