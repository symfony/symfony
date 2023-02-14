<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Tests\Adapter\ExtLdap;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Ldap\Adapter\ExtLdap\Connection;
use Symfony\Component\Ldap\Adapter\ExtLdap\EntryManager;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Exception\NotBoundException;

class EntryManagerTest extends TestCase
{
    public function testMove()
    {
        $this->expectException(LdapException::class);
        $this->expectExceptionMessage('Entry "$$$$$$" malformed, could not parse RDN.');
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('isBound')->willReturn(true);

        $entry = new Entry('$$$$$$');
        $entryManager = new EntryManager($connection);
        $entryManager->move($entry, 'a');
    }

    public function testGetResources()
    {
        $this->expectException(NotBoundException::class);
        $this->expectExceptionMessage('Query execution is not possible without binding the connection first.');
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('isBound')->willReturn(false);

        $entry = new Entry('$$$$$$');
        $entryManager = new EntryManager($connection);
        $entryManager->update($entry);
    }

    /**
     * @see https://tools.ietf.org/html/rfc4514#section-3
     *
     * @dataProvider moveWithRFC4514DistinguishedNameProvider
     */
    public function testMoveWithRFC4514DistinguishedName(string $dn, string $expectedRdn)
    {
        $connection = $this->createMock(Connection::class);

        $entry = new Entry($dn);
        $entryManager = new EntryManager($connection);

        $method = (new \ReflectionClass(EntryManager::class))->getMethod('parseRdnFromEntry');

        $cn = $method->invokeArgs($entryManager, [$entry, 'a']);

        $this->assertSame($expectedRdn, $cn);
    }

    public static function moveWithRFC4514DistinguishedNameProvider(): array
    {
        return [
            ['CN=Simple,DC=example,DC=net', 'CN=Simple'],
            ['CN=James \"Jim\" Smith\, III,DC=example,DC=net', 'CN=James \"Jim\" Smith\, III'],
            ['UID=jsmith,DC=example,DC=net', 'UID=jsmith'],
            ["CN=Before\0dAfter,DC=example,DC=net", "CN=Before\0dAfter"],
        ];
    }
}
