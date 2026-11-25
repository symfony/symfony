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
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

class AllTest extends TestCase
{
    public function testAllResetsPropertiesAccordingToRankAndPreservesExcludedProperties()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><style>section{font-weight:400}h2{color:red;direction:rtl;--custom:blue}h2.unset{all:unset}h2.initial{all:initial}h2.important{font-weight:700!important;all:initial}</style><section><h2 class="unset">Unset</h2><h2 class="initial">Initial</h2><h2 class="important">Important</h2></section>', new RenderingContext(400, 200, null, null, null, null, [], 'h2{font-weight:700}'));
        $document = $result->getDocument();
        self::assertSame('400', $result->getComputedStyle($document->querySelector('.unset'))['font-weight']);
        $initial = $result->getComputedStyle($document->querySelector('.initial'));
        self::assertSame('400', $initial['font-weight']);
        self::assertSame('#000000', $initial['color']);
        self::assertSame('rtl', $initial['direction']);
        self::assertSame('blue', $initial['--custom']);
        self::assertSame('700', $result->getComputedStyle($document->querySelector('.important'))['font-weight']);
    }

    public function testAllRevertUsesUserAgentDefaults()
    {
        $result = new HtmlRenderer()->render('<!DOCTYPE html><style>p{font-weight:400;color:red;all:revert}</style><p>Text</p>', new RenderingContext(400, 200, null, null, null, null, [], 'p{font-weight:700}'));
        $style = $result->getComputedStyle($result->getDocument()->querySelector('p'));
        self::assertSame('700', $style['font-weight']);
        self::assertSame('#000000', $style['color']);
    }
}
