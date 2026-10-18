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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Ldap\Entry;

class EntryTest extends TestCase
{
    public function testCaseSensitiveAttributeAccessors()
    {
        $mail = 'fabpot@symfony.com';
        $givenName = 'Fabien Potencier';

        $entry = new Entry('cn=fabpot,dc=symfony,dc=com', [
            'mail' => [$mail],
            'givenName' => [$givenName],
        ]);

        $this->assertFalse($entry->hasAttribute('givenname'));
        $this->assertTrue($entry->hasAttribute('givenname', false));

        $this->assertNull($entry->getAttribute('givenname'));
        $this->assertSame($givenName, $entry->getAttribute('givenname', false)[0]);

        $firstName = 'Fabien';

        $entry->setAttribute('firstName', [$firstName]);
        $this->assertSame($firstName, $entry->getAttribute('firstname', false)[0]);
        $entry->removeAttribute('firstName');
        $this->assertFalse($entry->hasAttribute('firstname', false));
    }

    public function testChangedAttributes()
    {
        $entry = new Entry('cn=fabpot,dc=symfony,dc=com', [
            'mail' => ['fabpot@symfony.com'],
            'sn' => ['fabpot'],
        ]);

        $this->assertSame(['mail' => ['fabpot@symfony.com'], 'sn' => ['fabpot']], $entry->getChangedAttributes());

        $entry->markAsUnchanged();
        $this->assertSame([], $entry->getChangedAttributes());

        $entry->setAttribute('givenName', ['Fabien']);
        $entry->setAttribute('sn', ['potencier']);
        $entry->removeAttribute('givenName');

        $this->assertSame(['sn' => ['potencier']], $entry->getChangedAttributes());
        $this->assertSame(['mail' => ['fabpot@symfony.com'], 'sn' => ['potencier']], $entry->getAttributes());
    }

    public function testChangedAttributesOfAnEntrySerializedByPreviousVersions()
    {
        $entry = unserialize('O:28:"Symfony\Component\Ldap\Entry":3:{s:40:"'."\0".'Symfony\Component\Ldap\Entry'."\0".'attributes";a:1:{s:4:"mail";a:1:{i:0;s:18:"fabpot@symfony.com";}}s:38:"'."\0".'Symfony\Component\Ldap\Entry'."\0".'lowerMap";a:1:{s:4:"mail";s:4:"mail";}s:32:"'."\0".'Symfony\Component\Ldap\Entry'."\0".'dn";s:27:"cn=fabpot,dc=symfony,dc=com";}');

        $this->assertSame('cn=fabpot,dc=symfony,dc=com', $entry->getDn());
        $this->assertSame(['mail' => ['fabpot@symfony.com']], $entry->getChangedAttributes());
    }
}
