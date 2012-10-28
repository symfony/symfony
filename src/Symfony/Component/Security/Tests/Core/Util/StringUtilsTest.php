<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Core\Util;

use Symfony\Component\Security\Core\Util\StringUtils;

class StringUtilsTest extends \PHPUnit_Framework_TestCase
{
    public function testEquals()
    {
        $this->assertTrue(StringUtils::equals('password', 'password'));
        $this->assertFalse(StringUtils::equals('password', 'foo'));
    }
}
