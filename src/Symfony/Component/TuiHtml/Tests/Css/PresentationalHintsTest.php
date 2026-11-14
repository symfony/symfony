<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;

final class PresentationalHintsTest extends TestCase
{
    public function testAttributesStandForDeclarations()
    {
        $view = $this->build('', '<textarea id="t" cols="30"></textarea><p id="p" align="center">x</p><table id="table" width="50%" border="0" bgcolor="#112233"><tr><td id="td" nowrap align="right" width="80">a</td></tr></table>');
        $value = static fn (string $id, string $property) => $view->getStyleSheet()->getComputedValue($view->getById($id), $property);
        $this->assertSame('30ch', $value('t', 'width'));
        $this->assertSame('center', $value('p', 'text-align'));
        $this->assertSame('50%', $value('table', 'width'));
        $this->assertSame('none', $value('table', 'border'));
        $this->assertSame('#112233', $value('table', 'background-color'));
        $this->assertSame('nowrap', $value('td', 'white-space'));
        $this->assertSame('right', $value('td', 'text-align'));
        $this->assertSame('80px', $value('td', 'width'));
    }

    public function testTheSizeAndTheFaceOfAFont()
    {
        $view = $this->build('font { display: block }', '<font id="a" size="5" face="small, serif">a</font><font id="b" size="+2">b</font><font id="c" size="-9">c</font><font id="d" size="big" face="x;y">d</font>');
        $value = static fn (string $id, string $property) => $view->getStyleSheet()->getComputedValue($view->getById($id), $property);
        $this->assertSame('x-large', $value('a', 'font-size'));
        $this->assertSame('small, serif', $value('a', 'font-family'));
        $this->assertSame('x-large', $value('b', 'font-size'), 'Relative to 3.');
        $this->assertSame('x-small', $value('c', 'font-size'), 'At least 1.');
        $this->assertNull($value('d', 'font-size'), 'Not a number.');
        $this->assertNull($value('d', 'font-family'), 'Only names.');
    }

    public function testAnyRuleWins()
    {
        $view = $this->build('@layer base { p { text-align: left } } textarea { width: 10ch }', '<textarea id="t" cols="30"></textarea><p id="p" align="center">x</p>');
        $value = static fn (string $id, string $property) => $view->getStyleSheet()->getComputedValue($view->getById($id), $property);
        $this->assertSame('10ch', $value('t', 'width'));
        $this->assertSame('left', $value('p', 'text-align'), 'Even in a layer.');
    }

    public function testOnlyValuesGoThrough()
    {
        $view = $this->build('', '<table id="table" bgcolor="red; display: none"><tr><td>a</td></tr></table>');
        $this->assertNull($view->getStyleSheet()->getComputedValue($view->getById('table'), 'background-color'));
        $this->assertNotSame('none', $view->getStyleSheet()->getComputedValue($view->getById('table'), 'display'));
    }

    private function build(string $css, string $html): HtmlView
    {
        return (new HtmlBuilder())->build('<style>'.$css.'</style>'.$html)->computeStyles();
    }
}
