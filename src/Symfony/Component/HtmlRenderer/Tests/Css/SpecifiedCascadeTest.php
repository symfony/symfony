<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\CssParser;
use Symfony\Component\HtmlRenderer\Css\CssRule;
use Symfony\Component\HtmlRenderer\Css\Declaration;
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\HtmlRenderer\Css\SpecifiedCascade;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;

class SpecifiedCascadeTest extends TestCase
{
    public function testSelectedRulesRetainTheirMediaPolicyAndReturnRawValues()
    {
        $rules = new CssParser()->parse('p{display:inline;color:var(--ink)}@media(min-width:9999px){p{display:block}}');
        self::assertSame('block', SpecifiedCascade::resolve($rules)['display']);
        self::assertSame('inline', SpecifiedCascade::resolve(array_filter($rules, static fn (CssRule $rule): bool => !$rule->media))['display']);
        self::assertSame('var(--ink)', SpecifiedCascade::resolve($rules)['color']);
    }

    public function testRawAndComputedCascadesAgreeOnLayersImportantAndInlinePriority()
    {
        $css = '@layer base,top;@layer base{p{color:red!important;background-color:red}}@layer top{p{color:blue!important;background-color:blue}}p{background-color:green}';
        $rules = new CssParser()->parse($css);
        $raw = SpecifiedCascade::resolve($rules);
        self::assertSame('red', $raw['color']);
        self::assertSame('green', $raw['background-color']);
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>'.$css.'</style><p>Target</p>');
        $computed = new StyleResolver()->resolve($document, 400, 200)[$document->querySelector('p')];
        self::assertSame($raw['color'], $computed['color']);
        self::assertSame($raw['background-color'], $computed['background-color']);
        self::assertSame('black', SpecifiedCascade::resolve($rules, [new Declaration('color', 'black', true)])['color']);
        $rules[] = new CssRule(Selector::parse('p'), [new Declaration('color', 'white', true)], [], 0, CssRule::UNLAYERED, true);
        self::assertSame('white', SpecifiedCascade::resolve($rules, [new Declaration('color', 'black', true)])['color']);
    }

    public function testShadowContextScopeAndHintsKeepTheSharedPriorityOrder()
    {
        $selector = Selector::parse('p');
        $outer = new CssRule($selector, [new Declaration('color', 'red')]);
        $inner = new CssRule($selector, [new Declaration('color', 'blue')], [], 1, CssRule::UNLAYERED, false, [], false, false, 1);
        self::assertSame('red', SpecifiedCascade::resolve([$outer, $inner])['color']);
        $outer = new CssRule($selector, [new Declaration('color', 'red', true)]);
        $inner = new CssRule($selector, [new Declaration('color', 'blue', true)], [], 1, CssRule::UNLAYERED, false, [], false, false, 1);
        self::assertSame('blue', SpecifiedCascade::resolve([$outer, $inner])['color']);
        self::assertSame('green', SpecifiedCascade::resolve([$outer, $inner], [new Declaration('color', 'green', true)])['color']);
        $scoped = new CssRule($selector, [new Declaration('color', 'purple')], [], 0, CssRule::UNLAYERED, false, [], true);
        self::assertSame('purple', SpecifiedCascade::resolve([$scoped, new CssRule($selector, [new Declaration('color', 'red')], [], 99)], [], [new Declaration('color', 'green')])['color']);
    }

    public function testRawResetFontAndPresentationHintOrderRemainsExplicit()
    {
        $rules = new CssParser()->parse('p{color:red;font-weight:bold;font:italic 12px serif;text-emphasis:filled circle blue;all:unset;--own:yes;direction:rtl}p{color:blue}');
        $values = SpecifiedCascade::resolve($rules, [], [new Declaration('color', 'green')], ['color', 'font-weight']);
        self::assertSame('blue', $values['color']);
        self::assertSame('inherit', $values['font-weight']);
        self::assertSame('initial', $values['font-style']);
        self::assertSame('yes', $values['--own']);
        self::assertSame('rtl', $values['direction']);
        self::assertSame('color', array_key_last($values));
    }
}
