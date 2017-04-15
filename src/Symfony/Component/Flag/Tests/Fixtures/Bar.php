<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Flag\Tests\Fixtures;

/**
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class Bar extends Foo
{
    const A = 'a';
    const B = 'b';
    const C = 'c';

    public static function getNotPrefixedFlags()
    {
        return array(
            array(self::A, 'A'),
            array(self::B, 'B'),
            array(self::C, 'C'),
        );
    }

    public static function getFlags()
    {
        return array_merge(self::getNotPrefixedFlags(), self::getPrefixedFlags());
    }

    public static function getBinarizedFlags()
    {
        return array(
            array(1, 'A'),
            array(2, 'B'),
            array(4, 'C'),
            array(8, 'FLAG_A'),
            array(16, 'FLAG_B'),
            array(32, 'FLAG_C'),
        );
    }
}
