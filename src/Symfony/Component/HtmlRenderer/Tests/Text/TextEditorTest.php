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
use Symfony\Component\HtmlRenderer\Event\KeyboardEvent;
use Symfony\Component\HtmlRenderer\Text\TextEditor;

final class TextEditorTest extends TestCase
{
    public function testGraphemeNavigationSelectionAndReplacement()
    {
        $editor = new TextEditor("Ae\u{0301}中");
        $editor->key(new KeyboardEvent('ArrowLeft'));
        $editor->key(new KeyboardEvent('ArrowLeft', null, true));
        self::assertSame([2, 1], $editor->getSelection());
        $editor->key(new KeyboardEvent('Text', 'B'));
        self::assertSame('AB中', $editor->getText());
        $editor->key(new KeyboardEvent('Delete'));
        self::assertSame('AB', $editor->getText());
    }

    public function testMultilineNavigationAndSelectAll()
    {
        $editor = new TextEditor("first\nsecond");
        $editor->key(new KeyboardEvent('Home'));
        $editor->key(new KeyboardEvent('ArrowUp'));
        self::assertSame([0, 0], $editor->getSelection());
        $editor->key(new KeyboardEvent('a', null, false, true));
        $editor->key(new KeyboardEvent('Text', 'Replaced'));
        self::assertSame('Replaced', $editor->getText());
    }
}
