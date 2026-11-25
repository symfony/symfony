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
use Symfony\Component\HtmlRenderer\Css\Anchor;

final class AnchorTest extends TestCase
{
    public function testReferencesRetainCaseAndExcludeImplicitNames()
    {
        self::assertSame(['--A', '--B'], Anchor::references(['position-anchor' => '--A', 'width' => 'calc(anchor-size(--B width) + 1px)', 'left' => 'anchor(--A right)', 'height' => 'anchor-size(height)']));
    }

    public function testFallbackDeclarationsUseLogicalAxesAndExcludeUnrelatedProperties()
    {
        self::assertSame([[
            ['top' => '2px', 'width' => '10px'],
            ['flip-inline'],
        ]], Anchor::alternatives(['writing-mode' => 'vertical-rl', 'position-try-fallbacks' => '--go flip-inline'], ['--go' => ['inset-inline-start' => '2px', 'width' => '10px', 'color' => 'red']]));
    }

    public function testAreaFallbackReplacesInsetsAndRetainsOtherStyleValues()
    {
        self::assertSame(['position-area' => 'left', 'color' => 'red'], Anchor::applyFallback(['top' => '2px', 'right' => '3px', 'bottom' => '4px', 'left' => '5px', 'position-area' => 'bottom right', 'color' => 'red'], ['position-area' => 'left']));
    }

    public function testAlternativeBudgetCountsOnlyResolvedAlternatives()
    {
        self::assertSame([], Anchor::alternatives(['position-try-fallbacks' => '--missing, flip-block'], [], 0));
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage('Position alternatives exceed the document box limit.');
        Anchor::alternatives(['position-try-fallbacks' => '--missing, flip-block, --go'], ['--go' => ['position-area' => 'left']], 0);
    }
}
