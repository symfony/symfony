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
use Symfony\Component\Ldap\LdapClient;
use Symfony\Polyfill\Php56\Php56 as p;

/**
 * @requires extension ldap
 */
class LdapClientTest extends TestCase
{
    public function testLdapEscape()
    {
        $ldap = new LdapClient();

        $this->assertEquals('\20foo\3dbar\0d(baz)*\20', $ldap->escape(" foo=bar\r(baz)* ", null, p::LDAP_ESCAPE_DN));
    }
}
