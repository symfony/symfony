<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class HostSuggestionsTest extends TestCase
{
    public function testHostSuggestionsReuseCompletionAndCanRestoreNativeDatalist()
    {
        $session = new HtmlRenderer()->createSession('<input list="words" value="p"><button>Next</button><datalist id="words"><option>PHP</option></datalist>', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $session->focus($input);
        $source = $session->getDocument()->saveHtml();
        $before = $session->render();
        $session->setSuggestions($input, ['Python']);
        self::assertSame('ython', $session->getCompletion($input));
        self::assertNotSame($before, $session->render());
        $unchanged = $session->render();
        $session->setSuggestions($input, ['Python']);
        self::assertSame($unchanged, $session->render());
        self::assertTrue($session->tab(false, false));
        self::assertSame('python', $session->getValue($input));
        self::assertSame($input, $session->getFocusedElement());
        self::assertTrue($session->tab(false, false));
        self::assertSame('button', $session->getFocusedElement()->localName);
        $session->focus($input);
        $session->setValue($input, 'p');
        $session->setSuggestions($input, []);
        self::assertNull($session->getCompletion($input));
        $session->setSuggestions($input, null);
        self::assertSame('HP', $session->getCompletion($input));
        self::assertSame($source, $session->getDocument()->saveHtml());
    }

    public function testCompletionRespectsCaretReadonlyAndCanceledKeyEvents()
    {
        $session = new HtmlRenderer()->createSession('<input value="p"><input type="password" value="p">', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $session->focus($input);
        $session->setSuggestions($input, ['Python']);
        $session->setTextSelection($input, 0, 0);
        self::assertTrue($session->tab(false, false));
        self::assertSame('p', $session->getValue($input));
        $session->focus($input);
        $session->setTextSelection($input, 1, 1);
        $input->setAttribute('readonly', '');
        self::assertTrue($session->tab(false, false));
        self::assertSame('p', $session->getValue($input));
        $session->focus($input);
        $input->removeAttribute('readonly');
        $session->on('keydown', static function ($event) { $event->preventDefault(); });
        self::assertTrue($session->tab(false, false));
        self::assertSame('p', $session->getValue($input));
        self::assertSame($input, $session->getFocusedElement());
        $password = $session->getDocument()->querySelector('input[type=password]');
        $session->setSuggestions($password, ['Private']);
        self::assertNull($session->getCompletion($password));
    }

    public function testInvalidSuggestionsAreRejectedAtomically()
    {
        $session = new HtmlRenderer(128)->createSession('<input value="p">', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $session->setSuggestions($input, ['PHP']);
        foreach ([[str_repeat('p', 129)], ["p\xff"], [42], array_fill(0, 2001, 'p')] as $suggestions) {
            try {
                $session->setSuggestions($input, $suggestions);
                self::fail('Invalid suggestions were accepted.');
            } catch (\InvalidArgumentException|\LengthException) {
                self::assertSame('HP', $session->getCompletion($input));
            }
        }
        $foreign = new HtmlRenderer()->createSession('<input>', new RenderingContext(300, 200))->getDocument()->querySelector('input');
        $this->expectException(\InvalidArgumentException::class);
        $session->setSuggestions($foreign, ['PHP']);
    }

    public function testTabBoundaryAndCancellationAreDistinguished()
    {
        $session = new HtmlRenderer()->createSession('<input>', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        self::assertTrue($session->tab(false, false));
        self::assertSame($input, $session->getFocusedElement());
        self::assertFalse($session->tab(false, false));
        self::assertFalse($session->tab(true, false));
        self::assertTrue($session->tab());
        $session->on('keydown', static function ($event) { $event->preventDefault(); });
        self::assertTrue($session->tab(false, false));
        self::assertSame($input, $session->getFocusedElement());
    }
}
