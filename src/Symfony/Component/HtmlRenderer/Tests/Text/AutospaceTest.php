<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Text;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Text\Autospace;

class AutospaceTest extends TestCase
{
    public function testSupportedKindsAndInvalidCombinations()
    {
        self::assertSame(['alpha', 'numeric'], Autospace::parse('normal'));
        self::assertSame(['alpha', 'numeric'], Autospace::parse('auto'));
        self::assertSame([], Autospace::parse('no-autospace'));
        self::assertSame(['alpha'], Autospace::parse('ideograph-alpha insert'));
        self::assertSame(['numeric'], Autospace::parse('ideograph-numeric replace'));
        self::assertSame([], Autospace::parse('punctuation'));
        foreach (['', 'insert', 'normal insert', 'ideograph-alpha ideograph-alpha', 'ideograph-alpha insert replace', 'unknown'] as $value) {
            self::assertNull(Autospace::parse($value), $value);
        }
    }

    public function testIdeographicBoundariesExcludeFullWidthCharacters()
    {
        self::assertSame('在 Symfony 8 中使用 TUI 组件', Autospace::apply('在Symfony 8中使用TUI组件', ['alpha', 'numeric']));
        self::assertSame('只有数字 2026 年Latin', Autospace::apply('只有数字2026年Latin', ['numeric']));
        self::assertSame('只有 Latin 字母2026年', Autospace::apply('只有Latin字母2026年', ['alpha']));
        self::assertSame('漢Ａ１字', Autospace::apply('漢Ａ１字', ['alpha', 'numeric']));
        self::assertSame('漢字', Autospace::apply('漢字', []));
    }

    public function testBoundaryProbeDoesNotChangeTheText()
    {
        self::assertSame(' ', Autospace::apply('字A', ['alpha'], true));
        self::assertSame(' ', Autospace::apply('8年', ['numeric'], true));
        self::assertSame('', Autospace::apply('字 A', ['alpha'], true));
        self::assertSame('', Autospace::apply('字A', ['numeric'], true));
    }
}
