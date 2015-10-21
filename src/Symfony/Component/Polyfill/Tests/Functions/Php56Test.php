<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Polyfill\Tests\Functions;

use Symfony\Component\Polyfill\Functions\Php56 as p;

class Php56Test extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideHashEqualsTrue
     */
    public function testHashEqualsTrue($known, $user)
    {
        $this->assertTrue(hash_equals($known, $user));
    }

    /**
     * @dataProvider provideHashEqualsFalse
     */
    public function testHashEqualsFalse($known, $user)
    {
        $this->assertFalse(@hash_equals($known, $user));
    }

    public function provideHashEqualsTrue()
    {
        return array(
            array('same', 'same'),
            array('', ''),
        );
    }

    public function provideHashEqualsFalse()
    {
        // Data from PHP.net's hash_equals tests.
        return array(
            array(123, 123),
            array(null, ''),
            array(null, null),
            array('not1same', 'not2same'),
            array('short', 'longer'),
            array('longer', 'short'),
            array('', 'notempty'),
            array('notempty', ''),
            array(123, 'NaN'),
            array('NaN', 123),
            array(null, 123),
        );
    }

    /**
     * @dataProvider provideLdapEscapeValues
     */
    public function testLdapEscape($subject, $ignore, $flags, $expected)
    {
        $this->assertSame($expected, ldap_escape($subject, $ignore, $flags));
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
            array('foo=bar(baz)*', null, p::LDAP_ESCAPE_DN, 'foo\3dbar(baz)*'),
            array('foo=bar(baz)*', null, null, '\66\6f\6f\3d\62\61\72\28\62\61\7a\29\2a'),
            array('foo=bar(baz)*', null, p::LDAP_ESCAPE_DN | p::LDAP_ESCAPE_FILTER, 'foo\3dbar\28baz\29\2a'),
            array('foo=bar(baz)*', null, p::LDAP_ESCAPE_FILTER, 'foo=bar\28baz\29\2a'),
            array('foo=bar(baz)*', 'ao', null, '\66oo\3d\62a\72\28\62a\7a\29\2a'),
        );
    }
}
