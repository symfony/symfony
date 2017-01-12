<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Tests;

use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;
use Symfony\Component\Ldap\Adapter\ExtLdap\Collection;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\NotBoundException;
use Symfony\Component\Ldap\LdapInterface;

/**
 * @requires extension ldap
 */
class AdapterTest extends LdapTestCase
{
    public function testLdapEscape()
    {
        $ldap = new Adapter();

        $this->assertEquals('\20foo\3dbar\0d(baz)*\20', $ldap->escape(" foo=bar\r(baz)* ", null, LdapInterface::ESCAPE_DN));
    }

    /**
     * @group functional
     */
    public function testLdapQuery()
    {
        $ldap = new Adapter($this->getLdapConfig());

        $ldap->getConnection()->bind('cn=admin,dc=symfony,dc=com', 'symfony');
        $query = $ldap->createQuery('dc=symfony,dc=com', '(&(objectclass=person)(ou=Maintainers))', array());
        $result = $query->execute();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);

        $entry = $result[0];
        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertEquals(array('Fabien Potencier'), $entry->getAttribute('cn'));
        $this->assertEquals(array('fabpot@symfony.com', 'fabien@potencier.com'), $entry->getAttribute('mail'));
    }

    /**
     * @group functional
     */
    public function testLdapQueryIterator()
    {
        $ldap = new Adapter($this->getLdapConfig());

        $ldap->getConnection()->bind('cn=admin,dc=symfony,dc=com', 'symfony');
        $query = $ldap->createQuery('dc=symfony,dc=com', '(&(objectclass=person)(ou=Maintainers))', array());
        $result = $query->execute();
        $iterator = $result->getIterator();
        $iterator->rewind();
        $entry = $iterator->current();
        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertEquals(array('Fabien Potencier'), $entry->getAttribute('cn'));
        $this->assertEquals(array('fabpot@symfony.com', 'fabien@potencier.com'), $entry->getAttribute('mail'));
    }

    /**
     * @group functional
     */
    public function testLdapQueryWithoutBind()
    {
        $ldap = new Adapter($this->getLdapConfig());
        $this->setExpectedException(NotBoundException::class);
        $query = $ldap->createQuery('dc=symfony,dc=com', '(&(objectclass=person)(ou=Maintainers))', array());
        $query->execute();
    }
}
