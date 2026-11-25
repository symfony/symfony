<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Render;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Render\FacadeRenderer;
use Symfony\Component\Tui\Render\Renderer;

final class FacadeGradientPolicyTest extends TestCase
{
    public function testFacadeUsesNativeNamedPaletteWithoutRemappingExplicitRgb()
    {
        foreach (['red' => 205 / 255, 'rgb(255,0,0)' => 1.0, '#ff0000' => 1.0] as $color => $red) {
            $view = new HtmlBuilder()->useHtmlRenderer()->build('<div style="width:4ch;height:1;background:linear-gradient('.$color.','.$color.')"></div>');
            new FacadeRenderer(new Renderer())->renderFrame($view->getRoot(), 10, 3);
            $operations = $view->getRoot()->getPresentation()->getResult()->getDisplayList()->getOperations();
            $gradient = array_values(array_filter($operations, static fn (array $op): bool => 'gradient' === $op['type']))[0];
            self::assertSame([[$red, 0.0, 0.0], 1.0], $gradient['gradient']['stops'][0][0]);
            self::assertSame([[$red, 0.0, 0.0], 1.0], $gradient['gradient']['stops'][1][0]);
        }
    }
}
