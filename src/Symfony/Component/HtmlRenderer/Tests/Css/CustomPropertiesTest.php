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
use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Css\CustomProperties;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;

class CustomPropertiesTest extends TestCase
{
    public function testDependentTraversalDistinguishesAnEmptySeedFromFalseValuedSeeds()
    {
        $inputs = ['width' => 'var(--seed)', 'margin' => 'var(--missing,var(--seed))', 'unused' => 'var(--present,var(--seed))', '--alias' => 'var(--seed)', 'height' => 'var(--alias)'];
        $available = ['--seed' => '1px', '--present' => '2px', '--alias' => '1px'];
        self::assertSame([], CustomProperties::dependents($inputs, $available, []));
        self::assertSame(['--seed' => false, 'width' => true, 'margin' => true, '--alias' => true, 'height' => true], CustomProperties::dependents($inputs, $available, ['--seed' => false]));
    }

    public function testAnIdleTimelinePreservesStandaloneComputedStyles()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>section{--ink:blue;--size:12.5px;color:var(--ink);font-size:var(--size)}p{margin:1.25px 2.5px;border:1px solid currentColor}p::before{content:"Prefix";color:var(--ink)}</style><section><p>First</p><p>Second</p></section>');
        $plain = new StyleResolver();
        $timed = new StyleResolver();
        $without = $plain->resolve($document, 400, 200);
        $with = $timed->resolve($document, 400, 200, [], new Timeline(), 0);
        foreach ($without as $element) {
            self::assertSame($without[$element], $with[$element]);
            self::assertSame($plain->getPseudoStyle($element, 'before'), $timed->getPseudoStyle($element, 'before'));
        }
    }

    public function testQuotedParenthesesDoNotTerminateVariableFallbacks()
    {
        self::assertSame('"a)b"', CustomProperties::substitute('var(--missing,"a)b")', []));
        self::assertSame('"var(--missing)"', CustomProperties::substitute('"var(--missing)"', []));
    }

    public function testInvalidDependencyUsesConsumerFallbackAndKeepsTokensSeparate()
    {
        self::assertSame('red', CustomProperties::substitute('var(--a, red)', ['--a' => 'var(--missing)']));
        self::assertSame('1px solid', CustomProperties::substitute('var(--a)var(--b)', ['--a' => '1px', '--b' => 'solid']));
    }
}
