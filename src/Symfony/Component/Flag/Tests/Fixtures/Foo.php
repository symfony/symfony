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
class Foo
{
    const FLAG_A = 1;
    const FLAG_B = 2;
    const FLAG_C = 4;

    public static function getPrefixedFlags()
    {
        return array(
            array(Bar::FLAG_A, 'FLAG_A'),
            array(Bar::FLAG_B, 'FLAG_B'),
            array(Bar::FLAG_C, 'FLAG_C'),
        );
    }
}
