<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Html\HtmlView;

final class BoundDocumentFontTest extends TestCase
{
    public function testMappedStylesUseNativeFontInheritanceAndSilentWidgetClassesWithoutMutatingSource()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>section{font-weight:bold;font-style:italic}.plain{font-weight:normal;font-style:normal}.hot:hover{font-weight:700}</style><section><p id="p">Text</p></section>');
        $view = new HtmlBuilder()->build($document->saveHtml());
        $document = new \ReflectionProperty(HtmlView::class, 'document')->getValue($view);
        $source = $document->saveHtml();
        $sheet = $view->getStyleSheet();
        $widget = $view->getById('p');
        $sheet->prepare($view->getRoot(), new \Symfony\Bridge\TuiHtml\Style\MediaContext());
        self::assertTrue($sheet->getStyles($widget)[0]->getBold());
        self::assertTrue($sheet->getStyles($widget)[0]->getItalic());
        $ids = new \ReflectionProperty($sheet, 'widgetDeclarations')->getValue($sheet);
        $computed = new \ReflectionProperty($sheet, 'declarations')->getValue($sheet);
        self::assertSame('700', $computed[$ids[$widget]]['font-weight']);
        self::assertSame('bold', $sheet->getComputedValue($widget, 'font-weight'));
        $widget->addStyleClass('plain');
        $sheet->prepare($view->getRoot(), new \Symfony\Bridge\TuiHtml\Style\MediaContext());
        self::assertFalse($sheet->getStyles($widget)[0]->getBold());
        self::assertFalse($sheet->getStyles($widget)[0]->getItalic());
        $widget->removeStyleClass('plain');
        $widget->addStyleClass('hot');
        $widget->setState('hover');
        $sheet->prepare($view->getRoot(), new \Symfony\Bridge\TuiHtml\Style\MediaContext());
        self::assertTrue($sheet->getStyles($widget)[0]->getBold());
        self::assertSame($source, $document->saveHtml());
    }

    public function testProgrammaticRulesAndRegisteredFontVariableReachNativeResolver()
    {
        $view = new HtmlBuilder()->build('<!DOCTYPE html><p id="p">Text</p>');
        $sheet = $view->getStyleSheet();
        $sheet->add('@property --weight { syntax:"<number>"; inherits:false; initial-value:700 } p{font-weight:var(--weight);font-style:italic;font-family:slant}');
        $sheet->prepare($view->getRoot(), new \Symfony\Bridge\TuiHtml\Style\MediaContext());
        $style = $sheet->getStyles($view->getById('p'))[0];
        self::assertTrue($style->getBold());
        self::assertTrue($style->getItalic());
        self::assertSame('slant', $style->getFont());
    }

    public function testAmbientFontIsUnspecifiedButExplicitNormalAndSilentControlValueRemainAuthoritative()
    {
        $view = new HtmlBuilder()->build('<!DOCTYPE html><style>input:invalid{font-weight:700}input:valid{font-weight:normal}</style><p id="plain">Text</p><input id="input" required>');
        $sheet = $view->getStyleSheet();
        $media = new \Symfony\Bridge\TuiHtml\Style\MediaContext();
        $sheet->prepare($view->getRoot(), $media);
        self::assertNull(($sheet->getStyles($view->getById('plain'))[0] ?? null)?->getBold());
        $input = $view->getById('input');
        self::assertTrue($sheet->getStyles($input)[0]->getBold());
        $input->setValue('Ada');
        $sheet->prepare($view->getRoot(), $media);
        self::assertFalse($sheet->getStyles($input)[0]->getBold());
        $document = new \ReflectionProperty(HtmlView::class, 'document')->getValue($view);
        self::assertFalse($document->getElementById('input')->hasAttribute('value'));
    }

    public function testUnifiedComputedStorageUsesCoreResolvedFontsAndReadbackKeepsRawSpelling()
    {
        $view = new HtmlBuilder()->build('<!DOCTYPE html><style>section{font-weight:300}p{font:italic 12px slant;font-weight:bolder}</style><section><p id="p">Text</p></section>');
        $sheet = $view->getStyleSheet();
        $sheet->prepare($view->getRoot(), new \Symfony\Bridge\TuiHtml\Style\MediaContext());
        $widget = $view->getById('p');
        $ids = new \ReflectionProperty($sheet, 'widgetDeclarations')->getValue($sheet);
        $declarations = new \ReflectionProperty($sheet, 'declarations')->getValue($sheet);
        self::assertSame('400', $declarations[$ids[$widget]]['font-weight']);
        self::assertSame('400', $sheet->getComputedValue($widget, 'font-weight'));
        self::assertSame('italic', $sheet->getComputedValue($widget, 'font-style'));
        self::assertSame('slant', $sheet->getComputedValue($widget, 'font-family'));
        self::assertTrue($sheet->getStyles($widget)[0]->getItalic());
    }

    public function testRawFontReadbackPreservesInheritanceVariablesShorthandsAndResets()
    {
        foreach ([
            'font-weight:inherit' => ['300', 'italic', null, false, true, null],
            'font-weight:bolder' => ['400', 'italic', null, false, true, null],
            '--weight:bold;font-weight:var(--weight)' => ['var(--weight)', 'italic', null, true, true, null],
            'font-weight:700;font:italic 12px slant' => ['normal', 'italic', 'slant', false, true, 'slant'],
            'all:initial' => ['initial', 'initial', 'initial', false, false, null],
            'all:unset' => ['300', 'italic', 'initial', false, true, null],
        ] as $declarations => $expected) {
            $view = new HtmlBuilder()->build('<!DOCTYPE html><section style="font-weight:300;font-style:italic"><p id="p" style="'.$declarations.'">Text</p></section>');
            $sheet = $view->getStyleSheet();
            $sheet->prepare($view->getRoot(), new \Symfony\Bridge\TuiHtml\Style\MediaContext());
            $widget = $view->getById('p');
            $style = $sheet->getStyles($widget)[0] ?? null;
            self::assertSame($expected, [$sheet->getComputedValue($widget, 'font-weight'), $sheet->getComputedValue($widget, 'font-style'), $sheet->getComputedValue($widget, 'font-family'), $style?->getBold(), $style?->getItalic(), $style?->getFont()], $declarations);
        }
    }

    public function testFontContainerQueriesRefreshAfterActualLayoutMetricsChange()
    {
        foreach (['30ch', '30'] as $minimum) {
            $view = new HtmlBuilder()->build('<!DOCTYPE html><style>#container{container-type:inline-size}@container(min-width:'.$minimum.'){p{font-weight:700}}</style><div id="container"><p id="p">Text</p></div>');
            $sheet = $view->getStyleSheet();
            $sheet->prepare($view->getRoot(), new \Symfony\Bridge\TuiHtml\Style\MediaContext());
            $target = $view->getById('p');
            self::assertNotTrue(($sheet->getStyles($target)[0] ?? null)?->getBold());
            $container = $view->getById('container');
            $sheet->afterLayout($view->getRoot(), static fn ($widget) => $widget === $container ? new \Symfony\Component\Tui\Render\WidgetRect(0, 0, 40, 2) : null);
            self::assertTrue($sheet->getStyles($target)[0]->getBold());
            $sheet->afterLayout($view->getRoot(), static fn ($widget) => $widget === $container ? new \Symfony\Component\Tui\Render\WidgetRect(0, 0, 20, 2) : null);
            self::assertNotTrue(($sheet->getStyles($target)[0] ?? null)?->getBold());
        }
    }
}
