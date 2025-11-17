<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

eval(<<<'EOPHP'
namespace PHPUnit\Util;

class Test
{
    public static function getGroups()
    {
        return array();
    }
}
EOPHP
);

@trigger_error('root deprecation', \E_USER_DEPRECATED);

class FooTestCase
{
    public function testLegacyFoo()
    {
        @trigger_error('silenced foo deprecation', \E_USER_DEPRECATED);
        trigger_error('unsilenced foo deprecation', \E_USER_DEPRECATED);
        @trigger_error('silenced foo deprecation', \E_USER_DEPRECATED);
        trigger_error('unsilenced foo deprecation', \E_USER_DEPRECATED);
    }

    public function testNonLegacyBar()
    {
        @trigger_error('silenced bar deprecation', \E_USER_DEPRECATED);
        trigger_error('unsilenced bar deprecation', \E_USER_DEPRECATED);
    }
}

$foo = new FooTestCase();
$foo->testLegacyFoo();
$foo->testNonLegacyBar();
