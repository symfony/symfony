<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\CounterStyles;

class CounterStyleMetadataTest extends TestCase
{
    public function testInertExtendsMetadataDoesNotChangeANumericSystemOrItsDescendants()
    {
        $styles = new CounterStyles();
        $styles->add([
            'binary' => ['system' => 'numeric', 'symbols' => ['0', '1'], 'extends' => 'decimal'],
            'decorated' => ['system' => 'extends', 'extends' => 'binary', 'prefix' => '<', 'suffix' => '>'],
            'padded' => ['system' => 'extends', 'extends' => 'decorated', 'pad' => [4, '0']],
        ]);
        self::assertSame('110', $styles->format(6, 'binary'));
        self::assertSame('<110>', $styles->marker(6, 'decorated'));
        self::assertSame('<0110>', $styles->marker(6, 'padded'));
    }
}
