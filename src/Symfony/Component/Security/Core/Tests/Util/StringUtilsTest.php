<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Util\StringUtils;

/**
 * Data from PHP.net's hash_equals tests.
 *
 * @group legacy
 */
class StringUtilsTest extends TestCase
{
    public function dataProviderTrue()
    {
        return array(
            array('same', 'same'),
            array('', ''),
            array(123, 123),
            array(null, ''),
            array(null, null),
        );
    }

    public function dataProviderFalse()
    {
        return array(
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
     * @dataProvider dataProviderTrue
     */
    public function testEqualsTrue($known, $user)
    {
        $this->assertTrue(StringUtils::equals($known, $user));
    }

    /**
     * @dataProvider dataProviderFalse
     */
    public function testEqualsFalse($known, $user)
    {
        $this->assertFalse(StringUtils::equals($known, $user));
    }
}
