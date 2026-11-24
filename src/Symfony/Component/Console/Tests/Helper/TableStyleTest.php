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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\TableStyle;

class TableStyleTest extends TestCase
{
    public function testSetZeroPaddingChar()
    {
        $style = new TableStyle();

        $this->assertSame($style, $style->setPaddingChar('0'));
        $this->assertSame('0', $style->getPaddingChar());
    }

    public function testSetEmptyPaddingChar()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The padding char must not be empty.');

        (new TableStyle())->setPaddingChar('');
    }

    public function testSetPadTypeWithInvalidType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid padding type. Expected one of (STR_PAD_LEFT, STR_PAD_RIGHT, STR_PAD_BOTH).');
        (new TableStyle())->setPadType(31);
    }
}
