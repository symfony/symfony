<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\Selector;
use Symfony\Component\HtmlRenderer\Html\ShadowRoots;

final class ShadowCompositionTest extends TestCase
{
    public function testOwnedViewPreservesCallerDomAndLightNodeIdentity()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><x-card><template shadowrootmode="open"><h2><slot name="title">Fallback</slot></h2><slot>Fallback body</slot></template><b id="light" slot="title">Title</b><p>Body</p></x-card>');
        $html = $document->saveHtml();
        $host = $document->querySelector('x-card');
        $light = $document->querySelector('#light');
        $text = $light->firstChild;
        $view = (new ShadowRoots())->createView($document);
        $mirror = $view['document']->querySelector('h2 b');
        self::assertNotSame($document, $view['document']);
        self::assertSame($html, $document->saveHtml());
        self::assertSame($host, $light->parentElement);
        self::assertSame($light, $document->querySelector('#light'));
        self::assertSame($light, $view['owners'][$mirror]);
        self::assertSame($text, $view['owners'][$mirror->firstChild]);
        self::assertSame($mirror, $view['mirrors'][$light]);
        self::assertSame($view['roots'][$host]->querySelector('slot[name]'), $view['parents'][$light]);
        self::assertInstanceOf(\Dom\DocumentFragment::class, $view['roots'][$host]);
        self::assertSame($document, $view['roots'][$host]->ownerDocument);
        self::assertNull($view['roots'][$host]->querySelector('h2')->getAttribute(Selector::TREE_ATTRIBUTE));
    }

    public function testCachedShadowIdentitySurvivesUnrelatedSourceEdits()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><x-card><template shadowrootmode="open"><button id="shadow">Press</button></template></x-card><p id="other">Other</p>');
        $host = $document->querySelector('x-card');
        $composer = new ShadowRoots();
        $first = $composer->createView($document);
        $shadow = $first['roots'][$host]->querySelector('button');
        $document->querySelector('#other')->textContent = 'Changed';
        $second = $composer->createView($document);
        self::assertSame($shadow, $second['roots'][$host]->querySelector('button'));
        self::assertSame($shadow, $second['owners'][$second['document']->querySelector('#shadow')]);
        self::assertSame($first['fingerprint'], $second['fingerprint']);
        self::assertNull($document->getElementById('shadow'));
        self::assertFalse($shadow->isConnected);
    }

    public function testMarkupEditReplacesOnlyItsOwnCachedRoot()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><x-a><template shadowrootmode="open"><span>A</span></template></x-a><x-b><template shadowrootmode="open"><span>B</span></template></x-b>');
        $a = $document->querySelector('x-a');
        $b = $document->querySelector('x-b');
        $composer = new ShadowRoots();
        $first = $composer->createView($document);
        $oldA = $first['roots'][$a]->querySelector('span');
        $oldB = $first['roots'][$b]->querySelector('span');
        $a->querySelector('template')->innerHTML = '<em>New</em>';
        $second = $composer->createView($document);
        self::assertSame('New', $second['roots'][$a]->querySelector('em')->textContent);
        self::assertFalse(isset($second['mirrors'][$oldA]));
        self::assertSame($oldB, $second['roots'][$b]->querySelector('span'));
    }

    public function testNestedSlotsPartsAndDepthsUseOriginalCachedNodes()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><x-outer><template shadowrootmode="open"><slot name="title">Fallback</slot><x-inner exportparts="dot: badge"><template shadowrootmode="closed"><span part="dot">Dot</span><b part="secret">Secret</b></template></x-inner></template><strong slot="title">Light</strong></x-outer>');
        $outer = $document->querySelector('x-outer');
        $view = (new ShadowRoots())->createView($document);
        $inner = $view['roots'][$outer]->querySelector('x-inner');
        $dot = $view['roots'][$inner]->querySelector('span');
        $mirror = $view['mirrors'][$dot];
        self::assertSame([1 => 1, 2 => 2], $view['depths']);
        self::assertSame('LightDotSecret', $view['document']->querySelector('x-outer')->textContent);
        self::assertSame('dot', $mirror->getAttribute(Selector::PART_ATTRIBUTE.'1'));
        self::assertSame('badge', $mirror->getAttribute(Selector::PART_ATTRIBUTE.'0'));
        self::assertNull($dot->getAttribute(Selector::PART_ATTRIBUTE.'0'));
        self::assertSame($inner, $view['parents'][$dot]);
    }

    public function testFragmentMutationsChangeFingerprintWithoutChangingSourceMarkup()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><x-card><template shadowrootmode="open"><span>Before</span></template></x-card>');
        $host = $document->querySelector('x-card');
        $composer = new ShadowRoots();
        $first = $composer->createView($document);
        $source = $document->saveHtml();
        $shadow = $first['roots'][$host]->querySelector('span');
        $shadow->textContent = 'After';
        $second = $composer->createView($document);
        self::assertSame($source, $document->saveHtml());
        self::assertNotSame($first['fingerprint'], $second['fingerprint']);
        self::assertSame('After', $second['document']->querySelector('span')->textContent);
        self::assertSame($shadow, $second['roots'][$host]->querySelector('span'));
    }

    public function testRetainedFingerprintDetectsMutationWithoutRebuildingTheOwnedView()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><x-card><template shadowrootmode="open"><span>Before</span></template></x-card>');
        $host = $document->querySelector('x-card');
        $composer = new ShadowRoots();
        $view = $composer->createView($document);
        self::assertSame($view['fingerprint'], $composer->getFingerprint());
        $view['roots'][$host]->querySelector('span')->textContent = 'After';
        self::assertNotSame($view['fingerprint'], $composer->getFingerprint());
        self::assertSame('Before', $view['document']->querySelector('span')->textContent);
        self::assertSame($composer->getFingerprint(), $composer->createView($document)['fingerprint']);
    }

    public function testDetachedHostsArePrunedFromThePersistentCache()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><x-card><template shadowrootmode="open"><span>Shadow</span></template></x-card>');
        $host = $document->querySelector('x-card');
        $composer = new ShadowRoots();
        $first = $composer->createView($document);
        $weak = \WeakReference::create($first['roots'][$host]);
        unset($first);
        $host->remove();
        $second = $composer->createView($document);
        gc_collect_cycles();
        self::assertCount(0, $second['roots']);
        self::assertNull($weak->get());
    }

    public function testInvalidModesAndInertTemplatesDoNotAcquireRoots()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><button><template shadowrootmode="open">Invalid host</template></button><x-card><template shadowrootmode="invalid">Invalid</template><template shadowrootmode="open">First</template><template shadowrootmode="closed">Second</template></x-card><template>Ordinary</template>');
        $html = $document->saveHtml();
        $view = (new ShadowRoots())->createView($document);
        self::assertCount(1, $view['roots']);
        self::assertSame('First', $view['document']->querySelector('x-card')->textContent);
        self::assertSame($html, $document->saveHtml());
    }

    public function testOnlyFinalComposedNodesRemainActiveWhenAnEarlierLightRootIsUnassigned()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><x-outer><x-unused><template shadowrootmode="open"><span>Hidden</span></template></x-unused><template shadowrootmode="open"><p>Visible</p></template></x-outer>');
        $outer = $document->querySelector('x-outer');
        $unused = $document->querySelector('x-unused');
        $composer = new ShadowRoots();
        $view = $composer->createView($document);
        self::assertCount(1, $view['roots']);
        self::assertTrue(isset($view['roots'][$outer]));
        self::assertFalse(isset($view['mirrors'][$unused]));
        self::assertCount(1, $view['depths']);
        self::assertSame('Visible', $view['document']->querySelector('x-outer')->textContent);
        $unused->querySelector('template')->innerHTML = '<b>Changed hidden root</b>';
        self::assertSame($view['fingerprint'], $composer->createView($document)['fingerprint']);
    }

    public function testAssignedSlotsExcludeUnusedLightAndFallbackNodesFromActiveMaps()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><x-card><template shadowrootmode="open"><slot name="title"><span>Fallback</span></slot></template><b slot="title">Actual</b><p id="unused">Unassigned</p></x-card>');
        $host = $document->querySelector('x-card');
        $unused = $document->querySelector('#unused');
        $view = (new ShadowRoots())->createView($document);
        self::assertFalse(isset($view['mirrors'][$unused]));
        self::assertFalse(isset($view['mirrors'][$view['roots'][$host]->querySelector('span')]));
        self::assertTrue($unused->isConnected);
        self::assertSame($unused, $document->querySelector('#unused'));
    }

    public function testAggregateParsedBytesAreBoundedIncludingCachedFragmentMutations()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><x-a><template shadowrootmode="open">1234567890</template></x-a><x-b><template shadowrootmode="open">1234567890</template></x-b>');
        try {
            (new ShadowRoots(15))->createView($document);
            self::fail('The two parsed roots exceed the aggregate budget.');
        } catch (\LengthException) {
            self::assertSame('1234567890', $document->querySelector('template')->innerHTML);
        }
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><x-a><template shadowrootmode="open"><b>x</b></template></x-a>');
        $composer = new ShadowRoots(32);
        $view = $composer->createView($document);
        $view['roots'][$document->querySelector('x-a')]->querySelector('b')->textContent = str_repeat('x', 100);
        $this->expectException(\LengthException::class);
        $composer->createView($document);
    }

    public function testFailedViewPrunesDetachedRootsWithoutDiscardingUnchangedRoots()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><x-a><template shadowrootmode="open"><b>A</b></template></x-a><x-b><template shadowrootmode="open"><b>B</b></template></x-b>');
        $a = $document->querySelector('x-a');
        $b = $document->querySelector('x-b');
        $composer = new ShadowRoots(32);
        $first = $composer->createView($document);
        $weak = \WeakReference::create($first['roots'][$a]);
        $unchanged = $first['roots'][$b]->querySelector('b');
        unset($first);
        $a->remove();
        $document->body->appendChild($error = $document->createElement('x-error'));
        $error->innerHTML = '<template shadowrootmode="open">'.str_repeat('x', 100).'</template>';
        try {
            $composer->createView($document);
            self::fail('The new root exceeds the aggregate budget.');
        } catch (\LengthException) {
            gc_collect_cycles();
            self::assertNull($weak->get());
        }
        $error->remove();
        $second = $composer->createView($document);
        self::assertSame($unchanged, $second['roots'][$b]->querySelector('b'));
    }

    public function testSlotDistributionCannotCombineTwoAllowedDepthsPastTheLimit()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><x-card><template shadowrootmode="open"></template><section></section></x-card>');
        $document->querySelector('template')->innerHTML = str_repeat('<div>', 300).'<slot></slot>'.str_repeat('</div>', 300);
        $parent = $document->querySelector('section');
        for ($depth = 0; $depth < 300; ++$depth) {
            $parent->appendChild($next = $document->createElement('span'));
            $parent = $next;
        }
        $this->expectException(\LengthException::class);
        (new ShadowRoots())->createView($document);
    }

    public function testTreeCountAndComposedDepthAreBounded()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html>'.str_repeat('<x-card><template shadowrootmode="open">x</template></x-card>', 257));
        $view = (new ShadowRoots())->createView($document);
        self::assertCount(256, $view['roots']);
        self::assertCount(256, $view['depths']);
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><div></div>');
        $parent = $document->querySelector('div');
        for ($depth = 0; $depth < 513; ++$depth) {
            $parent->appendChild($next = $document->createElement('div'));
            $parent = $next;
        }
        $this->expectException(\LengthException::class);
        (new ShadowRoots())->createView($document);
    }
}
