<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Provider;

use Symfony\Component\Ldap\LdapClient;

/**
 * @requires extension ldap
 */
class LdapClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideLdapEscapeValues
     */
    public function testLdapEscape($subject, $ignore, $flags, $expected)
    {
        $ldap = new LdapClient();
        $this->assertSame($expected, $ldap->escape($subject, $ignore, $flags));
    }

    /**
     * Provides values for the ldap_escape shim. These tests come from the official
     * extension.
     *
     * @see https://github.com/php/php-src/blob/master/ext/ldap/tests/ldap_escape_dn.phpt
     * @see https://github.com/php/php-src/blob/master/ext/ldap/tests/ldap_escape_all.phpt
     * @see https://github.com/php/php-src/blob/master/ext/ldap/tests/ldap_escape_both.phpt
     * @see https://github.com/php/php-src/blob/master/ext/ldap/tests/ldap_escape_filter.phpt
     * @see https://github.com/php/php-src/blob/master/ext/ldap/tests/ldap_escape_ignore.phpt
     *
     * @return array
     */
    public function provideLdapEscapeValues()
    {
        return array(
            array('foo=bar(baz)*', null, LdapClient::LDAP_ESCAPE_DN, 'foo\3dbar(baz)*'),
            array('foo=bar(baz)*', null, null, '\66\6f\6f\3d\62\61\72\28\62\61\7a\29\2a'),
            array('foo=bar(baz)*', null, LdapClient::LDAP_ESCAPE_DN | LdapClient::LDAP_ESCAPE_FILTER, 'foo\3dbar\28baz\29\2a'),
            array('foo=bar(baz)*', null, LdapClient::LDAP_ESCAPE_FILTER, 'foo=bar\28baz\29\2a'),
            array('foo=bar(baz)*', 'ao', null, '\66oo\3d\62a\72\28\62a\7a\29\2a'),
        );
    }
}
