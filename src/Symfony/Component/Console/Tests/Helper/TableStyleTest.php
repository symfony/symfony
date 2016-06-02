<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use Symfony\Component\Console\Helper\TableStyle;

class TableStyleTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException        \InvalidArgumentException
     * @expectedExceptionMessage Invalid padding type. Expected one of (STR_PAD_LEFT, STR_PAD_RIGHT, STR_PAD_BOTH).
     */
    public function testSetPadTypeWithInvalidType()
    {
        $style = new TableStyle();
        $style->setPadType('TEST');
    }
}
