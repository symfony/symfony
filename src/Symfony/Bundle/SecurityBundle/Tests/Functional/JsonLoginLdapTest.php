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

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Ldap\Adapter\AdapterInterface;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\ConnectionInterface;
use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\InvalidCredentialsException;
use Symfony\Component\Ldap\Security\CheckLdapCredentialsListener;

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
        $connectionMock = $this->createStub(ConnectionInterface::class);
        $collection = new class([new Entry('', ['uid' => ['spomky']])]) extends \ArrayObject implements CollectionInterface {
            public function toArray(): array
            {
                return $this->getArrayCopy();
            }
        };
        $queryMock = $this->createStub(QueryInterface::class);
        $queryMock
            ->method('execute')
            ->willReturn($collection)
        ;
        $ldapAdapterMock = $this->createStub(AdapterInterface::class);
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

    public function testLdapUsersOnlyLeavesOtherProvidersToTheRegularPasswordChecker()
    {
        if (!property_exists(CheckLdapCredentialsListener::class, 'ldapUsersOnly')) {
            $this->markTestSkipped('symfony/ldap 8.2 is required.');
        }

        $client = $this->createClient(['test_case' => 'JsonLoginLdap', 'root_config' => 'ldap_users_only.yml', 'debug' => true]);
        $checked = $this->mockLdapAdapter($client->getContainer());

        $client->request('POST', '/login', [], [], ['CONTENT_TYPE' => 'application/json'], '{"user": {"login": "bob", "password": "db-pass"}}');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame([], $checked->getArrayCopy());
    }

    public function testLdapUsersOnlyStillRejectsAWrongPasswordFromAnotherProvider()
    {
        if (!property_exists(CheckLdapCredentialsListener::class, 'ldapUsersOnly')) {
            $this->markTestSkipped('symfony/ldap 8.2 is required.');
        }

        $client = $this->createClient(['test_case' => 'JsonLoginLdap', 'root_config' => 'ldap_users_only.yml', 'debug' => true]);
        $checked = $this->mockLdapAdapter($client->getContainer());

        $client->request('POST', '/login', [], [], ['CONTENT_TYPE' => 'application/json'], '{"user": {"login": "bob", "password": "nope"}}');

        $this->assertSame(401, $client->getResponse()->getStatusCode());
        $this->assertSame([], $checked->getArrayCopy());
    }

    public function testLdapUsersOnlyStillBindsLdapUsers()
    {
        if (!property_exists(CheckLdapCredentialsListener::class, 'ldapUsersOnly')) {
            $this->markTestSkipped('symfony/ldap 8.2 is required.');
        }

        $client = $this->createClient(['test_case' => 'JsonLoginLdap', 'root_config' => 'ldap_users_only.yml', 'debug' => true]);
        $checked = $this->mockLdapAdapter($client->getContainer());

        $client->request('POST', '/login', [], [], ['CONTENT_TYPE' => 'application/json'], '{"user": {"login": "spomky", "password": "ldap-pass"}}');

        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertSame(['spomky'], $checked->getArrayCopy());
    }

    /**
     * Installs a directory holding a single "spomky" entry whose password is "ldap-pass".
     *
     * @return \ArrayObject<int, string> the identifiers whose password was checked against it
     */
    private function mockLdapAdapter(ContainerInterface $container): \ArrayObject
    {
        $checked = new \ArrayObject();

        $connection = new class($checked) implements ConnectionInterface {
            public function __construct(private \ArrayObject $checked)
            {
            }

            public function isBound(): bool
            {
                return true;
            }

            public function bind(?string $dn = null, #[\SensitiveParameter] ?string $password = null): void
            {
                if (!preg_match('/^uid=([^,]++),/', (string) $dn, $m)) {
                    // the search bind, not a credentials check
                    return;
                }

                $this->checked[] = $m[1];

                if ('spomky' !== $m[1] || 'ldap-pass' !== $password) {
                    throw new InvalidCredentialsException();
                }
            }

            public function saslBind(?string $dn = null, #[\SensitiveParameter] ?string $password = null, ?string $mech = null, ?string $realm = null, ?string $authcId = null, ?string $authzId = null, ?string $props = null): void
            {
            }

            public function whoami(): string
            {
                return '';
            }
        };

        $adapter = $this->createStub(AdapterInterface::class);
        $adapter->method('getConnection')->willReturn($connection);
        $adapter->method('escape')->willReturnArgument(0);
        $adapter->method('createQuery')->willReturnCallback(function (string $dn, string $query): QueryInterface {
            $entries = str_contains($query, 'spomky') ? [new Entry('uid=spomky,dc=onfroy,dc=net', ['uid' => ['spomky']])] : [];

            $collection = new class($entries) extends \ArrayObject implements CollectionInterface {
                public function toArray(): array
                {
                    return $this->getArrayCopy();
                }
            };

            $queryStub = $this->createStub(QueryInterface::class);
            $queryStub->method('execute')->willReturn($collection);

            return $queryStub;
        });

        $container->set(Adapter::class, $adapter);

        return $checked;
    }
}
