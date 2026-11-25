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
use Symfony\Component\HtmlRenderer\Event\KeyboardEvent;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class DatalistTest extends TestCase
{
    public function testCompletionPreservesTypedCaseAndIsAcceptedOnlyAtTheEnd(): void
    {
        $session = (new HtmlRenderer())->createSession('<input list="langs"><button>Next</button><datalist id="langs"><option value="PHP"><option>Python</option><option value="Perl" disabled></datalist>', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $session->focus($input);
        $session->key(new KeyboardEvent('p', 'p'));
        self::assertSame('HP', $session->getCompletion($input));
        $session->key(new KeyboardEvent('y', 'y'));
        self::assertSame('thon', $session->getCompletion($input));
        $session->key(new KeyboardEvent('Tab'));
        self::assertSame('python', $session->getValue($input));
        self::assertSame($input, $session->getFocusedElement());
        self::assertNull($session->getCompletion($input));
        $session->key(new KeyboardEvent('Tab'));
        self::assertSame('button', $session->getFocusedElement()->localName);
    }

    public function testCompletionRespectsMaxlengthWithoutTrappingKeyboardFocus(): void
    {
        $session = (new HtmlRenderer())->createSession('<input list="words" maxlength="2" value="p"><button>Next</button><datalist id="words"><option>Python</option></datalist>', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $session->focus($input);
        $session->key(new KeyboardEvent('Tab'));
        self::assertSame('py', $session->getValue($input));
        $session->key(new KeyboardEvent('Tab'));
        self::assertSame('button', $session->getFocusedElement()->localName);
    }

    public function testNativeMutationAndReadonlyAndCaretDoNotAcceptStaleSuggestions(): void
    {
        $session = (new HtmlRenderer())->createSession('<input list="langs" value="p"><button>Next</button><datalist id="langs"><option>PHP</option></datalist>', new RenderingContext(300, 200));
        $input = $session->getDocument()->querySelector('input');
        $session->focus($input);
        $session->setTextSelection($input, 0, 0);
        $session->key(new KeyboardEvent('ArrowRight'));
        self::assertSame('p', $session->getValue($input));
        $session->getDocument()->querySelector('option')->setAttribute('disabled', '');
        self::assertNull($session->getCompletion($input));
        $session->getDocument()->querySelector('option')->removeAttribute('disabled');
        $input->setAttribute('readonly', '');
        $session->key(new KeyboardEvent('Tab'));
        self::assertSame('button', $session->getFocusedElement()->localName);
        self::assertSame('p', $session->getValue($input));
    }
}
