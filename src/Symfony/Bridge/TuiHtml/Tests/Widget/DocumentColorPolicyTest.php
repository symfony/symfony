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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Css\Value;
use Symfony\Bridge\TuiHtml\Render\TerminalColorPalette;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Render\RenderContext;

final class DocumentColorPolicyTest extends TestCase
{
    #[DataProvider('provideColorExpressions')]
    public function testColorFunctionsUseTheExistingTerminalPaletteWhenExplicitlySelected(string $value, ColorScheme $scheme)
    {
        $media = new MediaContext(20, 4, $scheme);
        $expected = Value::color($value, $media)->toRgb();
        $session = (new HtmlRenderer())->createSession('<p style="margin:0;line-height:16px;color:'.htmlspecialchars($value).'">X</p>', $this->legacyContext());
        $widget = (new DocumentWidget($session))->setMediaContext($media);
        $lines = $widget->render(new RenderContext(20, 4));
        $cells = TerminalCellSnapshot::normalize(implode("\r\n", $lines), 20, 4);
        self::assertSame('X', $cells[0][0][0]);
        self::assertSame([$expected['r'], $expected['g'], $expected['b']], $cells[0][0][1]);
        $result = $session->render();
        $widget->render(new RenderContext(20, 4));
        self::assertSame($result, $session->render());
    }

    public static function provideColorExpressions(): iterable
    {
        foreach ([ColorScheme::Light, ColorScheme::Dark] as $scheme) {
            foreach (['light-dark(blue,yellow)', 'color-mix(in srgb, blue, white)', 'rgb(from blue r g b)'] as $value) {
                yield $scheme->value.' '.$value => [$value, $scheme];
            }
        }
    }

    public function testChangingTheSchemeRefreshesLookupWithoutReplacingItsStableIdentity()
    {
        $session = (new HtmlRenderer())->createSession('<p style="color:light-dark(blue,yellow)">X</p>', $this->legacyContext());
        $widget = (new DocumentWidget($session))->setMediaContext(new MediaContext(20, 4, ColorScheme::Dark));
        $context = new RenderContext(20, 4);
        $widget->render($context);
        $paragraph = $session->getDocument()->querySelector('p');
        self::assertSame('#cdcd00', $session->render()->getComputedStyle($paragraph)['color']);
        $lookup = $session->getContext()->colorLookup;
        $widget->setMediaContext(new MediaContext(20, 4, ColorScheme::Light));
        $widget->render($context);
        self::assertSame('#0000ee', $session->render()->getComputedStyle($paragraph)['color']);
        self::assertSame($lookup, $session->getContext()->colorLookup);
    }

    public function testCallerAliasesAndExplicitLookupRetainPriority()
    {
        $context = new RenderingContext(160, 64, null, null, null, null, [], '', ['default' => '#123456', 'blue-500' => '#654321']);
        $session = (new HtmlRenderer())->createSession('<p style="color:default">X</p><p style="color:blue-500">Y</p>', $context);
        (new DocumentWidget($session))->render(new RenderContext(20, 4));
        $paragraphs = $session->getDocument()->querySelectorAll('p');
        self::assertSame('#123456', $session->render()->getComputedStyle($paragraphs[0])['color']);
        self::assertSame('#654321', $session->render()->getComputedStyle($paragraphs[1])['color']);
        $lookup = static fn (string $name, string $scheme): ?array => 'blue' === $name ? [[0.25, 0.5, 0.75], 1.0] : null;
        $context = new RenderingContext(160, 64, null, null, null, null, [], '', [], null, null, $lookup);
        $session = (new HtmlRenderer())->createSession('<p style="color:light-dark(blue,yellow)">X</p>', $context);
        $widget = (new DocumentWidget($session))->setMediaContext(new MediaContext(20, 4, ColorScheme::Light));
        $widget->render(new RenderContext(20, 4));
        self::assertSame($lookup, $session->getContext()->colorLookup);
        self::assertSame('#4080bf', $session->render()->getComputedStyle($session->getDocument()->querySelector('p'))['color']);
    }

    private function legacyContext(): RenderingContext
    {
        return new RenderingContext(160, 64, null, null, null, null, [], '', [], null, null, TerminalColorPalette::lookupStandardName(...));
    }
}
