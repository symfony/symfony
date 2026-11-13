<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Css\ContainerQuery;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Html\HtmlView;

final class StyleQueryTest extends TestCase
{
    public function testParsing()
    {
        $query = ContainerQuery::parse('card style(--theme: dark) and not style(--compact)');
        $this->assertSame('card', $query->name);
        $this->assertNull($query->condition);
        $this->assertSame([[false, '--theme', 'dark'], [true, '--compact', null]], $query->styles);
        $this->assertFalse($query->any);

        $this->assertTrue(ContainerQuery::parse('style(--a: 1) or style(--b: 2)')->any);
        $this->assertTrue(ContainerQuery::parse('style(--a: 1) or style(--b: 2)')->matchesStyle(['--b' => ' 2 ']));
        $this->assertFalse(ContainerQuery::parse('style(--a: 1) and style(--b: 2)')->matchesStyle(['--b' => '2']));
    }

    public function testSizeAndStyleConditionsJoinedByAnd()
    {
        $query = ContainerQuery::parse('card (width > 10) and style(--x: 1) and (height > 2)');
        $this->assertSame('card', $query->name);
        $this->assertNotNull($query->condition);
        $this->assertTrue($query->condition->usesBlockAxis());
        $this->assertSame([[false, '--x', '1']], $query->styles);

        $this->assertSame([[true, '--y', null]], ContainerQuery::parse('not style(--y) and (width > 10)')->styles);
    }

    public function testInvalidConditionsThrow()
    {
        $this->expectException(InvalidArgumentException::class);
        ContainerQuery::parse('(width > 10) and style(--x: 1) or style(--y: 2)');
    }

    public function testRulesFollowTheValuesOfTheContainer()
    {
        $view = $this->build(<<<'CSS'
            .theme-dark { --theme: dark }
            .card { container-name: card; --compact: yes }
            .card section { --compact: initial }
            @container style(--compact) { .inner { text-decoration: underline } }
            @container style(--theme: dark) { p { color: #ffffff } }
            @container card style(--compact) { .inner { font-weight: bold } }
            @container not style(--theme: dark) { p { color: #000000 } }
            CSS, '<div class="theme-dark"><p id="dark">dark</p></div><div><p id="light">light</p></div><div class="card"><section><p id="inner" class="inner">in a card</p></section></div>');
        $value = static fn (string $id, string $property) => $view->getStyleSheet()->getComputedValue($view->getById($id), $property);

        $this->assertSame('#ffffff', $value('dark', 'color'), 'Its parent says dark.');
        $this->assertSame('#000000', $value('light', 'color'));
        $this->assertSame('bold', $value('inner', 'font-weight'), 'The nearest container named card, not the parent.');
        $this->assertNull($value('inner', 'text-decoration'), 'Unnamed: the parent, the section, which resets it.');
    }

    private function build(string $css, string $html): HtmlView
    {
        return (new HtmlBuilder())->build('<style>'.$css.'</style>'.$html)->computeStyles();
    }
}
