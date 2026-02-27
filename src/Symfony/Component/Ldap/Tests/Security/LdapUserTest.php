<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Tests\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Security\LdapUser;

class LdapUserTest extends TestCase
{
    public function testIsEqualToWorksOnUnserializedUser()
    {
        $user = new LdapUser(new Entry('uid=jonhdoe,ou=MyBusiness,dc=symfony,dc=com', []), 'jonhdoe', 'p455w0rd');
        $unserializedUser = unserialize(serialize($user));

        $this->assertTrue($unserializedUser->isEqualTo($user));
    }
}
