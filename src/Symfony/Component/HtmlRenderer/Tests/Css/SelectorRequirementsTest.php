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
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\HtmlRenderer\Css\SelectorRequirements;

class SelectorRequirementsTest extends TestCase
{
    public function testPrefilterNeverRejectsMatchingAlternativesOrEscapedNames()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><main><p class="A café" id="été">text</p><ol><li>x</li></ol></main>');
        $requirements = new SelectorRequirements();
        $names = SelectorRequirements::names($document);
        foreach (['main > p.A', ':is(ol,menu) > li:nth-last-child(1)', 'p:not(.missing)', '.missing, p.café', '#été'] as $source) {
            $selector = implode(', ', array_map(static fn (Selector $selector): string => $selector->forHtml(), Selector::parseList($source)));
            self::assertNotEmpty(iterator_to_array(Selector::queryAll($document, $selector)), $source);
            self::assertTrue($requirements->mayMatch($selector, $names), $source);
        }
        self::assertFalse($requirements->mayMatch('mroot > :nth-child(2)', $names));
        self::assertFalse($requirements->mayMatch(':is(menu,ul) > li', $names));
        self::assertTrue($requirements->mayMatch('svg|path', $names), 'Unknown namespace syntax is left to the native selector engine.');
    }
}
