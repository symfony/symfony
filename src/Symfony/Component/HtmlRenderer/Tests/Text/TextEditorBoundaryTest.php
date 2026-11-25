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

class TextEditorBoundaryTest extends TestCase
{
    public function testInsertedCombiningMarkAndJoinerKeepCaretWithinResultingGraphemes()
    {
        foreach ([['e', 1, "\u{0301}", "e\u{0301}"], ['👩💻', 1, "\u{200d}", '👩‍💻']] as [$text, $position, $insert, $expected]) {
            $editor = new TextEditor($text);
            $editor->setSelection($position, $position);
            self::assertTrue($editor->key(new KeyboardEvent('Text', $insert)));
            self::assertSame($expected, $editor->getText());
            self::assertSame([1, 1], $editor->getSelection());
            self::assertTrue($editor->key(new KeyboardEvent('Backspace')));
            self::assertSame('', $editor->getText());
            self::assertTrue($editor->key(new KeyboardEvent('z', null, false, true)));
            self::assertSame($expected, $editor->getText());
            self::assertSame([1, 1], $editor->getSelection());
        }
    }

    public function testUndoHistoryRemainsBoundedAndReadonlyCannotChangeIt()
    {
        $editor = new TextEditor('');
        for ($i = 0; $i < 300; ++$i) {
            $editor->key(new KeyboardEvent('Text', 'a'));
        }
        self::assertFalse($editor->key(new KeyboardEvent('z', null, false, true), true, true));
        self::assertSame(300, \strlen($editor->getText()));
        $undos = 0;
        while ($editor->key(new KeyboardEvent('z', null, false, true))) {
            ++$undos;
        }
        self::assertLessThanOrEqual(256, $undos);
        self::assertGreaterThan(0, \strlen($editor->getText()));
    }
}
