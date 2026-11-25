<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Animation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

class PreparedCascadeTest extends TestCase
{
    public function testPreparedWinnersRecomputeValuesAndInheritanceWithoutRetainingComputedResults()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@keyframes change{from{color:red;font-size:10px}to{color:blue;font-size:20px}}div{animation:change 1s linear both;--space:2px}span{font-size:1em;margin-inline:var(--space);direction:rtl}span::before{content:"before";color:inherit}</style><div><span>Child</span></div>');
        $full = new StyleResolver();
        $prepared = new StyleResolver();
        $fullTimeline = new Timeline();
        $preparedTimeline = new Timeline();
        $initial = $full->resolve($document, 400, 200, [], $fullTimeline, 0);
        $prepared->resolve($document, 400, 200, [], $preparedTimeline, 0, null, null, null, [], null, true);
        $prepared->releaseComputedStyles();
        foreach ([.25, .5, 1.0] as $time) {
            $expected = $full->resolve($document, 400, 200, [], $fullTimeline, $time);
            $actual = $prepared->resolvePrepared($preparedTimeline, $time);
            foreach ($initial as $element) {
                self::assertSame($expected[$element], $actual[$element]);
                self::assertSame($full->getCascadeRecord($element), $prepared->getCascadeRecord($element));
            }
        }
        $document->querySelector('style')->textContent = 'div{color:green}';
        $prepared->resolve($document, 400, 200);
        $this->expectException(InvalidArgumentException::class);
        $prepared->resolvePrepared($preparedTimeline, 2);
    }

    public function testOrdinaryResolutionDoesNotRetainAPreparedStage()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Plain</p>');
        $resolver = new StyleResolver();
        $resolver->resolve($document, 400, 200);
        $this->expectException(InvalidArgumentException::class);
        $resolver->resolvePrepared(new Timeline(), 0);
    }
}
