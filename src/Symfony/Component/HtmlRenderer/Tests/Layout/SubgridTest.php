<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class SubgridTest extends TestCase
{
    public function testRowSubgridAlignsNaturalRowsAndReactsToContentChanges()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>html,body,p{margin:0;font-size:10px;line-height:10px}.grid{display:grid;grid-template-columns:20px 20px;row-gap:2.5px}.card{display:grid;grid-template-columns:minmax(0,1fr);grid-template-rows:subgrid;grid-row:span 2}</style><div class="grid"><div class="card"><p>A</p><p id="left">L</p></div><div class="card"><p id="title">long long title</p><p id="right">R</p></div></div>');
        $session = (new HtmlRenderer())->createSession($document, new RenderingContext(100, 100));
        $result = $session->render();
        self::assertSame(32.5, $result->getBounds($document->getElementById('left'))[0]->y);
        self::assertSame(32.5, $result->getBounds($document->getElementById('right'))[0]->y);
        $document->getElementById('title')->textContent = 'B';
        $result = $session->render();
        self::assertSame(12.5, $result->getBounds($document->getElementById('left'))[0]->y);
        self::assertSame(12.5, $result->getBounds($document->getElementById('right'))[0]->y);
    }

    public function testExplicitFractionalRowTracksIncludeSubgridChromeOnce()
    {
        $result = (new HtmlRenderer())->render('<style>html,body{margin:0}.grid{display:grid;grid-template-columns:30px;grid-template-rows:10.5px 20.25px;row-gap:1.75px}.sub{display:grid;grid-template-rows:subgrid;grid-row:span 2;padding:1px 0;border:0.5px solid}</style><div class="grid"><div class="sub"><div id="a"></div><div id="b"></div></div></div>', new RenderingContext(100, 100));
        $document = $result->getDocument();
        self::assertSame(1.5, $result->getBounds($document->getElementById('a'))[0]->y);
        self::assertSame(12.25, $result->getBounds($document->getElementById('b'))[0]->y);
        self::assertSame(18.75, $result->getBounds($document->getElementById('b'))[0]->height);
    }

    public function testNestedRowSubgridPreservesRowsBeyondInheritedSpan()
    {
        $result = (new HtmlRenderer())->render('<style>html,body{margin:0;font-size:10px;line-height:10px}.grid{display:grid;grid-template-columns:20px 20px;gap:1px}.sub,.nested{display:grid;grid-template-columns:minmax(0,1fr);grid-template-rows:subgrid;grid-row:span 2}</style><div class="grid"><div class="sub"><div class="nested"><div>A</div><div id="nested">B</div></div></div><div class="sub"><div>long title</div><div id="peer">C</div></div></div>', new RenderingContext(100, 100));
        $document = $result->getDocument();
        self::assertSame(21.0, $result->getBounds($document->getElementById('peer'))[0]->y);
        self::assertSame(21.0, $result->getBounds($document->getElementById('nested'))[0]->y);
        $result = (new HtmlRenderer())->render('<style>html,body{margin:0;font-size:10px;line-height:10px}.grid{display:grid}.sub{display:grid;grid-template-rows:subgrid;grid-row:span 1}</style><div class="grid"><div class="sub"><div>A</div><div id="last">B</div></div></div>', new RenderingContext(100, 100));
        self::assertSame(10.0, $result->getBounds($result->getDocument()->getElementById('last'))[0]->y);
    }

    public function testDeepRowSubgridsPropagateAssignedRowsWithoutLosingContent()
    {
        $nested = '<div>A</div><div id="deep">B</div>';
        for ($level = 0; $level < 12; ++$level) {
            $nested = '<div class="sub">'.$nested.'</div>';
        }
        $result = (new HtmlRenderer())->render('<style>html,body{margin:0;font-size:10px;line-height:10px}.grid{display:grid;grid-template-columns:20px 20px;gap:1px}.sub{display:grid;grid-template-columns:minmax(0,1fr);grid-template-rows:subgrid;grid-row:span 2}</style><div class="grid">'.$nested.'<div class="sub"><div>long title</div><div id="peer">C</div></div></div>', new RenderingContext(100, 100));
        self::assertSame(21.0, $result->getBounds($result->getDocument()->getElementById('deep'))[0]->y);
        foreach ($result->getDocument()->querySelectorAll('.sub') as $subgrid) {
            self::assertSame(31.0, $result->getBounds($subgrid)[0]->height);
        }
    }

    public function testColumnSubgridTracksFollowParentChangesAndFractionalGap()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>html,body{margin:0}.grid{display:grid;grid-template-columns:10.5px 20.25px;column-gap:1.75px}.sub{display:grid;grid-template-columns:subgrid;grid-column:span 2}</style><div class="grid"><div class="sub"><div>A</div><div id="b">B</div></div></div>');
        $session = (new HtmlRenderer())->createSession($document, new RenderingContext(100, 100));
        $b = $document->getElementById('b');
        self::assertSame(12.25, $session->render()->getBounds($b)[0]->x);
        self::assertSame(20.25, $session->render()->getBounds($b)[0]->width);
        $document->querySelector('.grid')->setAttribute('style', 'grid-template-columns:30.5px 10.25px');
        self::assertSame(32.25, $session->render()->getBounds($b)[0]->x);
        self::assertSame(10.25, $session->render()->getBounds($b)[0]->width);
    }

    public function testSubgridOwnGapAndOutOfRangePlacementStayWithinInheritedColumns()
    {
        $result = (new HtmlRenderer())->render('<style>html,body{margin:0}.grid{display:grid;grid-template-columns:10.5px 20.25px;gap:2px}.sub{display:grid;grid-template-columns:subgrid;grid-column:span 2;gap:1px}.b{grid-column:99 / span 5}</style><div class="grid"><div class="sub"><div class="b"></div></div></div>', new RenderingContext(100, 100));
        $bounds = $result->getBounds($result->getDocument()->querySelector('.b'))[0];
        self::assertSame(0.0, $bounds->x);
        self::assertSame(31.75, $bounds->width);
    }

    public function testNestedSubgridIntrinsicContributionsReachParentAutoTracks()
    {
        $result = (new HtmlRenderer())->render('<style>html,body{margin:0}.grid{display:grid;grid-template-columns:auto auto;justify-content:start}.sub,.nested{display:grid;grid-template-columns:subgrid;grid-column:span 2}</style><div class="grid"><div class="sub"><div class="nested"><div>A</div><div id="wide">LONG</div></div></div><div id="next">x</div><div id="last">y</div></div>', new RenderingContext(100, 100));
        $document = $result->getDocument();
        self::assertSame(8.0, $result->getBounds($document->getElementById('wide'))[0]->x);
        self::assertSame(32.0, $result->getBounds($document->getElementById('last'))[0]->width);
        self::assertSame(8.0, $result->getBounds($document->getElementById('next'))[0]->width);
    }

    public function testSubgridDoesNotInheritAcrossNonGridParentAndReleasesOldTracks()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>html,body{margin:0}.grid{display:grid;grid-template-columns:10px 20px}.sub{display:grid;grid-template-columns:subgrid;grid-column:span 2}</style><div class="grid"><section><div class="sub"><div>A</div><div id="b">B</div></div></section></div>');
        $session = (new HtmlRenderer())->createSession($document, new RenderingContext(100, 100));
        self::assertSame(0.0, $session->render()->getBounds($document->getElementById('b'))[0]->x);
        $section = $document->querySelector('section');
        $section->replaceWith($section->firstElementChild);
        self::assertSame(10.0, $session->render()->getBounds($document->getElementById('b'))[0]->x);
        $document->querySelector('.grid')->setAttribute('style', 'display:block');
        self::assertSame(0.0, $session->render()->getBounds($document->getElementById('b'))[0]->x);
    }
}
