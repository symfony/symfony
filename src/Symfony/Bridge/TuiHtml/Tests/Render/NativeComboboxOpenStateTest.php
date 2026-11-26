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
use Symfony\Bridge\TuiHtml\Tests\Compatibility\FacadeScenario;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;

class NativeComboboxOpenStateTest extends TestCase
{
    public function testNativeOpeningClosingAndReopeningReachTheSemanticInputOnly()
    {
        foreach (['l' => true, 'l,down' => true, 'l,escape' => false, 'l,escape,down' => true] as $keys => $open) {
            foreach ([false, true] as $important) {
                $html = $this->html($important);
                [$style, $cityState, $otherState] = $this->computed($html, explode(',', $keys));
                self::assertSame($open, \in_array('open', $cityState, true));
                self::assertNotContains('open', $otherState);
                self::assertSame($open ? 'cyan' : '#e5e5e5', $style['border-top-color']);
                $options = ['columns' => 40, 'rows' => 15, 'focus' => '#city', 'keys' => $keys];
                $old = TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options), 40, 15, [229, 229, 229]);
                $new = TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options, true), 40, 15, [229, 229, 229]);
                self::assertSame($old[0][5], $new[0][5]);
            }
        }
    }

    public function testInlineBorderShorthandResetWinsOverTheNormalOpenRule()
    {
        foreach (['input[list]:open', '#city:open', 'body input#city[list]:open'] as $selector) {
            $html = str_replace(['border-color:cyan!important', 'input[list]:open'], ['border-color:cyan', $selector], $this->html(true));
            [$style, $cityState] = $this->computed($html, ['l']);
            self::assertContains('open', $cityState);
            self::assertSame('#e5e5e5', $style['border-top-color']);
            $options = ['columns' => 40, 'rows' => 15, 'focus' => '#city', 'keys' => 'l'];
            $old = TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options), 40, 15, [229, 229, 229]);
            $new = TerminalCellSnapshot::normalize(FacadeScenario::render($html, $options, true), 40, 15, [229, 229, 229]);
            self::assertSame([0, 205, 205], $old[0][5][1]);
            self::assertSame([229, 229, 229], $new[0][5][1]);
        }
    }

    private function html(bool $important): string
    {
        return '<style>'.($important ? '' : 'input[list]{border:1px solid}').'input[list]:open{border-color:cyan'.($important ? '!important' : '').'}</style><label for="city">City</label><input id="city" list="cities" style="'.($important ? 'border:1px solid;' : '').'max-width:24ch"><datalist id="cities"><option>Paris</option><option>Lyon</option><option>Lille</option></datalist><input id="other">';
    }

    private function computed(string $html, array $keys): array
    {
        $view = new HtmlBuilder()->useHtmlRenderer()->build($html);
        $terminal = new VirtualTerminal(40, 15);
        $tui = new Tui(null, $terminal, null, null, new FacadeRenderer(new Renderer()));
        try {
            $view->mount($tui);
            $tui->setFocus($view->querySelector('#city'));
            $tui->processRender();
            foreach ($keys as $key) {
                $tui->handleInput(match ($key) {
                    'down' => "\x1b[B", 'escape' => "\x1b", default => $key,
                });
                $tui->processRender();
            }
            $result = $view->getRoot()->getPresentation()->getResult();
            $city = $result->getDocument()->getElementById('city');
            $other = $result->getDocument()->getElementById('other');

            return [$result->getComputedStyle($city), explode(' ', $city->getAttribute(Selector::STATE_ATTRIBUTE) ?? ''), explode(' ', $other->getAttribute(Selector::STATE_ATTRIBUTE) ?? '')];
        } finally {
            $view->unmount();
            $tui->stop();
        }
    }
}
