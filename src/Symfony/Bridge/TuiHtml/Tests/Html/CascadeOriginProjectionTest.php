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
use Symfony\Bridge\TuiHtml\Style\MediaContext;

final class CascadeOriginProjectionTest extends TestCase
{
    public function testInheritedOriginsRespectExplicitResetsNumericLineHeightAndPseudoOverrides()
    {
        $view = new HtmlBuilder()->build('<!doctype html><style>section{color:red;font-weight:700;font-style:italic;line-height:1.5}#override{font-weight:normal}#override::before{content:"before";font-weight:inherit;color:inherit}#absolute{line-height:inherit}#length{font-size:20px}</style><section dir="rtl"><p id="unitless">inherited</p><p id="override">normal</p><div id="absolute"><p id="length">length</p></div><p id="zero" style="line-height:0">zero</p><p id="reset" style="line-height:initial;font-weight:initial">reset</p></section><p id="ambient">ambient</p>');
        $sheet = $view->getStyleSheet();
        $sheet->prepare($view->getRoot(), new MediaContext());
        self::assertSame('1.5', $sheet->getComputedValue($view->getById('unitless'), 'line-height'));
        self::assertSame('30px', $sheet->getComputedValue($view->getById('length'), 'line-height'));
        self::assertSame('0', $sheet->getComputedValue($view->getById('zero'), 'line-height'));
        self::assertSame('initial', $sheet->getComputedValue($view->getById('reset'), 'line-height'));
        self::assertSame('rtl', $sheet->getComputedValue($view->getById('unitless'), 'direction'));
        self::assertTrue($sheet->getStyles($view->getById('unitless'))[0]->getBold());
        self::assertFalse($sheet->getStyles($view->getById('override'))[0]->getBold());
        self::assertFalse($sheet->getStyles($view->getById('reset'))[0]->getBold());
        self::assertTrue($sheet->getStyles($view->getById('override'))[0]->getItalic());
        self::assertFalse($sheet->getElementStyles($view->getById('override'), 'before')[0]->getBold());
        self::assertNull($sheet->getStyles($view->getById('ambient'))[0]->getColor());
        self::assertNull($sheet->getStyles($view->getById('ambient'))[0]->getBold());
    }
}
