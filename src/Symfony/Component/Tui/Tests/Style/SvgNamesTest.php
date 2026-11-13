<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Style;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Css\CssStyleSheet;
use Symfony\Component\Tui\Style\SvgNames;

#[RequiresPhpExtension('dom')]
final class SvgNamesTest extends TestCase
{
    public function testHtmlSvgNamesAreAdjustedBeforeMatchingStyles()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><svg viewbox="0 0 20 10"><lineargradient gradientunits="userSpaceOnUse"/><filter><feflood id="a"/><feflood id="b"/><fegaussianblur stddeviation=".5"/></filter></svg>', \LIBXML_NOERROR);
        SvgNames::normalize($svg = $document->querySelector('svg'));

        $this->assertSame('0 0 20 10', $svg->getAttribute('viewBox'));
        $this->assertCount(2, $document->querySelectorAll('feFlood'));
        $this->assertCount(0, $document->querySelectorAll('feflood'));
        $this->assertSame('userSpaceOnUse', $document->querySelector('linearGradient')->getAttribute('gradientUnits'));
        $this->assertSame('.5', $document->querySelector('feGaussianBlur')->getAttribute('stdDeviation'));
        $sheet = CssStyleSheet::fromString('feFlood { flood-color: red }');
        $this->assertSame(['flood-color' => 'red'], $sheet->svgDeclarations($document->querySelector('#b'), ['flood-color']));
    }

    public function testXmlRetainsTheCaseItWasWrittenIn()
    {
        $document = \Dom\XMLDocument::createFromString('<svg xmlns="http://www.w3.org/2000/svg" viewbox="0 0 20 10"><feflood/></svg>');
        SvgNames::normalize($document->documentElement);

        $this->assertTrue($document->documentElement->hasAttribute('viewbox'));
        $this->assertFalse($document->documentElement->hasAttribute('viewBox'));
        $this->assertCount(1, $document->getElementsByTagName('feflood'));
    }
}
