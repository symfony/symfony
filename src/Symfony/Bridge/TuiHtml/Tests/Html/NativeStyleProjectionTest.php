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
use Symfony\Bridge\TuiHtml\Html\NativeStyleProjection;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;

class NativeStyleProjectionTest extends TestCase
{
    public function testProjectionDoesNotApplyAnUnchangedBorderExpansionTwice()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><div style="border:1px solid;margin:10px;margin-left:20px;flex-grow:initial"></div>');
        $resolver = new StyleResolver();
        $resolver->resolve($document, 640, 480);
        $style = NativeStyleProjection::project($resolver->getCascadeRecord($document->querySelector('div')));
        self::assertSame('1px solid', $style['border']);
        self::assertArrayNotHasKey('border-top-color', $style);
        self::assertArrayNotHasKey('border-top-style', $style);
        self::assertArrayNotHasKey('margin', $style);
        self::assertSame('10px', $style['margin-top']);
        self::assertSame('20px', $style['margin-left']);
        self::assertSame('10px', $style['margin-right']);
        self::assertSame('initial', $style['flex-grow']);
    }

    public function testLegacyOmittedBorderColorUsesTheBestExplicitCandidate()
    {
        foreach (['p{border-color:red;border:1px solid;border:2px dashed}', 'p{border-color:red!important;border:1px solid!important;border:2px dashed!important}', '@layer base{p{border-color:red}}p{border:1px solid;border:2px dashed}'] as $css) {
            $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>'.$css.'</style><p>text</p>');
            $resolver = new StyleResolver();
            $styles = $resolver->resolve($document, 640, 480);
            $element = $document->querySelector('p');
            self::assertSame('#000000', $styles[$element]['border-top-color'], 'The standalone CSS shorthand resets the earlier color.');
            $record = $resolver->getCascadeRecord($element);
            self::assertTrue($record['declarations']['border-top-color']['implicit']);
            self::assertSame('red', NativeStyleProjection::project($record)['border-top-color'], 'Only the compatibility facade retains the omitted-component behavior.');
        }
    }

    public function testAnExplicitCurrentColorNeverUsesTheCompatibilityCandidate()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>p{color:blue;border-color:red;border:1px solid currentColor}</style><p>text</p>');
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 640, 480);
        $element = $document->querySelector('p');
        $record = $resolver->getCascadeRecord($element);
        self::assertSame('blue', $styles[$element]['border-top-color']);
        self::assertFalse($record['declarations']['border-top-color']['implicit']);
        self::assertNull($record['declarations']['border-top-color']['explicit-candidate']);
        self::assertNotContains('red', NativeStyleProjection::project($record));
    }
}
