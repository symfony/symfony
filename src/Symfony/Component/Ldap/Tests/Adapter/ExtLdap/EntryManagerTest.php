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

class EntryManagerTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\Ldap\Exception\NotBoundException
     * @expectedExceptionMessage Query execution is not possible without binding the connection first.
     */
    public function testGetResources()
    {
        $connection = $this->getMockBuilder(Connection::class)->getMock();
        $connection
            ->expects($this->once())
            ->method('isBound')->willReturn(false);

        $entry = new Entry('$$$$$$');
        $entryManager = new EntryManager($connection);
        $entryManager->update($entry);
    }
}
