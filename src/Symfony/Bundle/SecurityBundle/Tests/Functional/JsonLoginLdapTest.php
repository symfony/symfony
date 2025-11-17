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

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Ldap\Adapter\AdapterInterface;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\ConnectionInterface;
use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;

class JsonLoginLdapTest extends AbstractWebTestCase
{
    public function testKernelBoot()
    {
        $kernel = self::createKernel(['test_case' => 'JsonLoginLdap', 'root_config' => 'config.yml']);
        $kernel->boot();

        $this->assertInstanceOf(Kernel::class, $kernel);
    }

    public function testDefaultJsonLdapLoginSuccess()
    {
        // Given
        $client = $this->createClient(['test_case' => 'JsonLoginLdap', 'root_config' => 'config.yml', 'debug' => true]);
        $container = $client->getContainer();
        $connectionMock = $this->createMock(ConnectionInterface::class);
        $collection = new class([new Entry('', ['uid' => ['spomky']])]) extends \ArrayObject implements CollectionInterface {
            public function toArray(): array
            {
                return $this->getArrayCopy();
            }
        };
        $queryMock = $this->createMock(QueryInterface::class);
        $queryMock
            ->method('execute')
            ->willReturn($collection)
        ;
        $ldapAdapterMock = $this->createMock(AdapterInterface::class);
        $ldapAdapterMock
            ->method('getConnection')
            ->willReturn($connectionMock)
        ;
        $ldapAdapterMock
            ->method('createQuery')
            ->willReturn($queryMock)
        ;
        $container->set(Adapter::class, $ldapAdapterMock);

        // When
        $client->request('POST', '/login', [], [], ['CONTENT_TYPE' => 'application/json'], '{"user": {"login": "spomky", "password": "foo"}}');
        $response = $client->getResponse();

        // Then
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Welcome @spomky!', 'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_USER']], json_decode($response->getContent(), true));
    }
}
