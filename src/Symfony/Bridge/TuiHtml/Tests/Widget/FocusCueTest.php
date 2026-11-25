<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Render\RenderContext;

class FocusCueTest extends TestCase
{
    public function testDefaultControlsHaveVisibleFocusColorsAndRestoreTheirUnfocusedFrame()
    {
        foreach (['<select id="control"><option>Choice</option></select>', '<button id="control">Save</button>', '<input id="control" type="checkbox">', '<input id="control" type="radio">', '<input id="control" type="submit" value="Go">'] as $html) {
            [$session, $widget, $context] = $this->document($html);
            $before = $widget->render($context);
            $source = $session->getDocument()->saveHtml();
            $session->focus($session->getDocument()->getElementById('control'));
            $cells = $this->cells($widget->render($context));
            $this->assertSame([0, 0, 0], $cells[0][0][1]);
            $this->assertSame([229, 229, 229], $cells[0][0][2]);
            $this->assertSame('none', $session->render()->getComputedStyle($session->getDocument()->getElementById('control'))['filter']);
            $session->focus(null);
            $this->assertSame($before, $widget->render($context));
            $this->assertSame($source, $session->getDocument()->saveHtml());
        }
    }

    public function testAuthoredColorsAndPseudoColorsOverrideTheUserAgentCue()
    {
        foreach (['<select id="control" style="color:red;background:blue"><option>Choice</option></select>', '<button id="control" style="color:red;background:blue">Save</button>', '<input id="control" type="checkbox" style="color:red;background:blue">'] as $html) {
            [$session, $widget, $context] = $this->document($html);
            $session->focus($session->getDocument()->getElementById('control'));
            $cells = $this->cells($widget->render($context));
            $this->assertSame([205, 0, 0], $cells[0][0][1]);
            $this->assertSame([0, 0, 238], $cells[0][0][2]);
        }
        [$session, $widget, $context] = $this->document('<style>button::before{color:cyan}</style><button id="control">Save</button>');
        $session->focus($session->getDocument()->getElementById('control'));
        $this->assertSame([0, 205, 205], $this->cells($widget->render($context))[0][0][1]);
    }

    public function testOpenPickerAndListboxDoNotInheritTheClosedFieldCue()
    {
        [$session, $widget, $context] = $this->document('<style>option{color:inherit}</style><select id="control"><option>A</option><option>B</option></select>');
        $select = $session->getDocument()->getElementById('control');
        $session->focus($select);
        $widget->render($context);
        $this->assertSame('#000000', $session->render()->getComputedStyle($select)['color']);
        $session->showPicker($select);
        $widget->render($context);
        $this->assertSame('#e5e5e5', $session->render()->getComputedStyle($select)['color']);
        foreach ($session->getDocument()->querySelectorAll('option') as $option) {
            $this->assertSame('#e5e5e5', $session->render()->getComputedStyle($option)['color']);
        }
        $session->hidePicker($select);
        $widget->render($context);
        $this->assertSame('#000000', $session->render()->getComputedStyle($select)['color']);
        $select->setAttribute('size', '2');
        $widget->render($context);
        $this->assertSame('#e5e5e5', $session->render()->getComputedStyle($select)['color']);
    }

    public function testMonochromeFocusHasAnUnderlineAndAuthoredDecorationCanOverrideIt()
    {
        foreach (['', '<style>button:focus{text-decoration:none}</style>'] as $css) {
            [$session, $widget, $context] = $this->document($css.'<button id="control">Save</button>');
            $widget->setMediaContext(new MediaContext(80, 24, ColorScheme::Dark, Density::Regular, false, true));
            $session->focus($session->getDocument()->getElementById('control'));
            $cell = $this->cells($widget->render($context))[0][0];
            $this->assertSame([0, 0, 0], $cell[2]);
            $this->assertSame('' === $css, \in_array('4', explode(';', $cell[3]), true));
            $this->assertSame('' === $css ? '4' : '', $cell[3], 'The monochrome underline uses the visible default foreground.');
        }
        $previous = getenv('NO_COLOR');
        try {
            putenv('NO_COLOR=1');
            [$session, $widget, $context] = $this->document('<select id="control"><option>Choice</option></select>');
            $session->focus($session->getDocument()->getElementById('control'));
            $this->assertContains('4', explode(';', $this->cells($widget->render($context))[0][0][3]));
        } finally {
            false === $previous ? putenv('NO_COLOR') : putenv('NO_COLOR='.$previous);
        }
    }

    public function testAuthorChromeAndAppearancePropertiesRemainUnchanged()
    {
        [$session, $widget, $context] = $this->document('<button id="control" style="appearance:none;padding:1ch;border:1px solid magenta">Save</button>');
        $button = $session->getDocument()->getElementById('control');
        $session->focus($button);
        $cells = $this->cells($widget->render($context));
        $style = $session->render()->getComputedStyle($button);
        $this->assertSame('none', $style['appearance']);
        $this->assertSame('1ch', $style['padding']);
        $this->assertSame([205, 0, 205], $cells[0][0][1]);
        $this->assertSame([0, 0, 0], $cells[0][0][2]);
        $this->assertSame([229, 229, 229], $cells[1][2][2]);
        $this->assertSame('content-box', $style['background-clip']);
    }

    public function testExplicitUserAgentPolicyDoesNotAcquireTheDefaultCue()
    {
        [$session, $widget, $context] = $this->document('<select id="control"><option>Choice</option></select>', 'html{color:default;line-height:16px}');
        $select = $session->getDocument()->getElementById('control');
        $session->focus($select);
        $widget->render($context);
        $style = $session->render()->getComputedStyle($select);
        $this->assertSame('#e5e5e5', $style['color']);
        $this->assertArrayNotHasKey('background-color', $style);
    }

    private function document(string $html, string $ua = ''): array
    {
        $session = (new HtmlRenderer())->createSession('<style>html{color:default}</style>'.$html, new RenderingContext(320, 128, null, null, null, null, [], $ua, ['default' => '#e5e5e5']));

        return [$session, new DocumentWidget($session), new RenderContext(40, 8)];
    }

    private function cells(array $lines): array
    {
        return TerminalCellSnapshot::normalize(implode("\r\n", $lines), 40, 8, [229, 229, 229], [0, 0, 0]);
    }
}
