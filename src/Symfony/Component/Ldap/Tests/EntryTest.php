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
}
