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
use Symfony\Bridge\TuiHtml\Style\Color;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Widget\RichTextWidget;

final class AnonymousCascadeTest extends TestCase
{
    public function testAnonymousTextKeepsItsOwnersUsedStylesWithoutInheritingItsBackground()
    {
        $view = new HtmlBuilder()->build('<!doctype html><style>@property --shade{syntax:"<color>";inherits:false;initial-value:blue}#owner{--shade:red;color:var(--shade);font-weight:700;font-style:italic;background:green}p{color:var(--shade)}</style><div id="owner">anonymous<p id="child">semantic child</p></div>');
        $owner = $view->getById('owner');
        $anonymous = $owner->all()[0];
        self::assertInstanceOf(RichTextWidget::class, $anonymous);
        $sheet = $view->getStyleSheet();
        $sheet->prepare($view->getRoot(), new MediaContext());
        $style = $sheet->getStyles($anonymous)[0];
        self::assertSame(Color::named('red')->toHex(), $style->getColor()->toHex());
        self::assertTrue($style->getBold());
        self::assertTrue($style->getItalic());
        self::assertNull($style->getBackground());
        self::assertSame(Color::named('blue')->toHex(), $sheet->getStyles($view->getById('child'))[0]->getColor()->toHex());
    }
}
