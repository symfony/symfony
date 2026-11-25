<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

final class EmbeddedDocumentRenderingTest extends TestCase
{
    public function testNestedDocumentsKeepTheirCascadeAndViewportAndStableIdentity()
    {
        $inner = '<style>p{color:green}@media(max-width:100px){p{color:blue}}</style><p id="inside">Inside</p>';
        $session = new HtmlRenderer()->createSession('<style>p{color:red}iframe{display:block;width:80px;height:60px;border:0}</style><p>Outside</p><iframe srcdoc="'.htmlspecialchars($inner).'"/>', new RenderingContext(300, 200));
        $frame = $session->getDocument()->querySelector('iframe');
        $source = $session->getDocument()->saveHtml();
        $result = $session->render();
        $child = $session->getEmbeddedSession($frame);
        self::assertNotNull($child);
        self::assertSame('blue', $child->render()->getComputedStyle($child->getDocument()->getElementById('inside'))['color']);
        self::assertSame([], $result->getComputedStyle($child->getDocument()->getElementById('inside')));
        self::assertSame(['Outside', 'Inside'], array_values(array_column(array_filter($result->getDisplayList()->getOperations(), static fn ($op) => 'text' === $op['type']), 'text')));
        self::assertSame($child, $session->getEmbeddedSession($frame));
        self::assertSame($result, $session->render());
        self::assertSame($source, $session->getDocument()->saveHtml());
        $frame->setAttribute('style', 'width:160px');
        self::assertSame($child, $session->getEmbeddedSession($frame));
        self::assertSame(80.0, $child->getContext()->width);
        $session->render();
        self::assertSame($child, $session->getEmbeddedSession($frame));
        self::assertSame(160.0, $child->getContext()->width);
        self::assertSame('green', $child->render()->getComputedStyle($child->getDocument()->getElementById('inside'))['color']);
        $frame->setAttribute('srcdoc', '<p>Changed</p>');
        $session->render();
        self::assertNotSame($child, $session->getEmbeddedSession($frame));
    }

    public function testDepthAndAggregateSourceBudgetApplyBeforeParsing()
    {
        $html = '<p>Deep</p>';
        for ($depth = 0; $depth < 5; ++$depth) {
            $html = '<p>Level</p><iframe srcdoc="'.htmlspecialchars($html).'"/>';
        }
        $session = new HtmlRenderer()->createSession($html, new RenderingContext(300, 200));
        $texts = array_column(array_filter($session->render()->getDisplayList()->getOperations(), static fn ($op) => 'text' === $op['type']), 'text');
        self::assertSame(['Level', 'Level', 'Level', 'Level'], $texts);
        $loader = new ResourceLoader(static fn () => '<p>'.str_repeat('a', 600).'</p>');
        $session = new HtmlRenderer(1000)->createSession('<iframe src="one"></iframe><iframe src="two"></iframe>', new RenderingContext(300, 200, null, $loader));
        $frames = $session->getDocument()->querySelectorAll('iframe');
        $session->render();
        self::assertNotNull($session->getEmbeddedSession($frames->item(0)));
        self::assertNull($session->getEmbeddedSession($frames->item(1)));
    }

    public function testParentHitTestingDoesNotReturnForeignNodes()
    {
        $session = new HtmlRenderer()->createSession('<iframe style="display:block;width:80px;height:60px;border:0" srcdoc="&lt;button&gt;Inside&lt;/button&gt;"></iframe>', new RenderingContext(300, 200));
        $frame = $session->getDocument()->querySelector('iframe');
        $result = $session->render();
        self::assertSame($frame, $result->hitTest(10, 10));
        $operations = $result->getDisplayList()->getOperations();
        self::assertContains('push-transform', array_column($operations, 'type'));
        self::assertContains('push-clip', array_column($operations, 'type'));
        $frame->remove();
        $session->render();
        self::assertNull($session->getEmbeddedSession($frame));
    }

    public function testResourcesAreSharedAndChildInvalidationRepaintsParent()
    {
        $reads = [];
        $loader = new ResourceLoader(static function (string $source) use (&$reads): string {
            $reads[] = $source;

            return 'input{color:green}';
        });
        $inner = '<link rel="stylesheet" href="shared.css"><input value="Before">';
        $session = new HtmlRenderer()->createSession('<link rel="stylesheet" href="shared.css"><iframe style="width:120px;height:80px" srcdoc="'.htmlspecialchars($inner).'"></iframe>', new RenderingContext(300, 200, null, $loader));
        $frame = $session->getDocument()->querySelector('iframe');
        $before = $session->render();
        $child = $session->getEmbeddedSession($frame);
        self::assertSame(['shared.css'], $reads);
        $child->setValue($child->getDocument()->querySelector('input'), 'After');
        $after = $session->render();
        self::assertNotSame($before, $after);
        self::assertContains('After', array_column($after->getDisplayList()->getOperations(), 'text'));
        self::assertSame($after, $session->render());
        $frame->removeAttribute('srcdoc');
        $session->render();
        self::assertNull($session->getEmbeddedSession($frame));
    }

    public function testEmptySrcdocAndAdoptionReleaseThePreviousSession()
    {
        $session = new HtmlRenderer()->createSession('<iframe srcdoc="&lt;p&gt;First&lt;/p&gt;"></iframe>', new RenderingContext(300, 200));
        $frame = $session->getDocument()->querySelector('iframe');
        $first = $session->getEmbeddedSession($frame);
        $frame->setAttribute('srcdoc', '');
        $empty = $session->getEmbeddedSession($frame);
        self::assertNotSame($first, $empty);
        self::assertSame(['push-clip', 'pop-clip'], array_column($empty->render()->getDisplayList()->getOperations(), 'type'));
        $other = \Dom\HTMLDocument::createFromString('<!doctype html><body>Other</body>');
        $other->body->appendChild($other->adoptNode($frame));
        $session->render();
        self::assertNull($session->getEmbeddedSession($frame));
    }

    public function testEmbeddedTopLayerRemainsInsideViewportAndOuterTransform()
    {
        $session = new HtmlRenderer()->createSession('<iframe style="position:absolute;left:30px;top:40px;width:80px;height:60px;border:0;transform:translate(5px, 6px);opacity:.5" srcdoc="&lt;div popover&gt;Popup&lt;/div&gt;"></iframe>', new RenderingContext(300, 200));
        $frame = $session->getDocument()->querySelector('iframe');
        $child = $session->getEmbeddedSession($frame);
        $child->showPopover($child->getDocument()->querySelector('[popover]'));
        $operations = $session->render()->getDisplayList()->getOperations();
        $text = array_search('Popup', array_column($operations, 'text'), true);
        self::assertNotFalse($text);
        $types = array_column($operations, 'type');
        self::assertCount(2, array_filter($types, static fn ($type) => 'push-transform' === $type));
        self::assertContains('push-opacity', $types);
        $clips = array_values(array_filter($operations, static fn ($op) => 'push-clip' === $op['type']));
        self::assertSame(['type' => 'push-clip', 'x' => 30.0, 'y' => 40.0, 'width' => 80.0, 'height' => 60.0, 'clip-x' => true, 'clip-y' => true], $clips[0]);
        self::assertSame(['pop-transform', 'pop-clip', 'pop-opacity', 'pop-transform'], \array_slice($types, -4));
    }

    public function testDocumentCountAndLaterParentGrowthCannotExceedSharedBudget()
    {
        $session = new HtmlRenderer()->createSession(str_repeat('<iframe srcdoc=""></iframe>', 65), new RenderingContext(300, 200));
        $session->render();
        $frames = $session->getDocument()->querySelectorAll('iframe');
        self::assertNotNull($session->getEmbeddedSession($frames->item(63)));
        self::assertNull($session->getEmbeddedSession($frames->item(64)));
        $frames->item(0)->remove();
        $session->render();
        self::assertNotNull($session->getEmbeddedSession($frames->item(63)));

        $loader = new ResourceLoader(static fn () => '<p>'.str_repeat('a', 600).'</p>');
        $session = new HtmlRenderer(1000)->createSession('<iframe src="one"></iframe>', new RenderingContext(300, 200, null, $loader));
        $frame = $session->getDocument()->querySelector('iframe');
        self::assertNotNull($session->getEmbeddedSession($frame));
        $session->getDocument()->body->appendChild($session->getDocument()->createTextNode(str_repeat('b', 500)));
        $session->render();
        self::assertNull($session->getEmbeddedSession($frame));
    }

    public function testExplicitParentRenderDetectsNativeChildDocumentMutations()
    {
        $session = new HtmlRenderer()->createSession('<iframe srcdoc="&lt;p&gt;Before&lt;/p&gt;"></iframe>', new RenderingContext(300, 200));
        $frame = $session->getDocument()->querySelector('iframe');
        $before = $session->render();
        $child = $session->getEmbeddedSession($frame);
        $child->getDocument()->querySelector('p')->textContent = 'After';
        $after = $session->render();
        self::assertNotSame($before, $after);
        self::assertContains('After', array_column($after->getDisplayList()->getOperations(), 'text'));
        self::assertSame($after, $session->render());
    }

    public function testNestedNativeMutationsInvalidateOnlyTheContainingFrames()
    {
        $inner = '<iframe srcdoc="&lt;p&gt;Before&lt;/p&gt;"></iframe>';
        $session = new HtmlRenderer()->createSession('<iframe srcdoc="'.htmlspecialchars($inner).'"></iframe>', new RenderingContext(300, 200));
        $frame = $session->getDocument()->querySelector('iframe');
        $child = $session->getEmbeddedSession($frame);
        $grandchild = $child->getEmbeddedSession($child->getDocument()->querySelector('iframe'));
        $before = $session->render();
        $grandchild->getDocument()->querySelector('p')->textContent = 'After';
        self::assertNotSame($before, $session->render());
        self::assertContains('After', array_column($session->render()->getDisplayList()->getOperations(), 'text'));
        self::assertSame($child, $session->getEmbeddedSession($frame));
    }

    public function testChildMutationCannotAmplifyTheAggregateDocumentBudget()
    {
        $session = new HtmlRenderer(1000)->createSession('<iframe srcdoc="&lt;p&gt;One&lt;/p&gt;"></iframe><iframe srcdoc="&lt;p&gt;Two&lt;/p&gt;"></iframe>', new RenderingContext(300, 200));
        $frames = $session->getDocument()->querySelectorAll('iframe');
        $first = $session->getEmbeddedSession($frames->item(0));
        $second = $session->getEmbeddedSession($frames->item(1));
        $first->getDocument()->querySelector('p')->textContent = str_repeat('a', 450);
        $second->getDocument()->querySelector('p')->textContent = str_repeat('b', 450);
        $this->expectException(\LengthException::class);
        $session->render();
    }

    public function testEmbeddedRootHasAnIndependentScrollableViewport()
    {
        $inner = '<p>One</p><p>Two</p><p id="last">Last</p>';
        $session = new HtmlRenderer()->createSession('<iframe style="width:100px;height:40px;border:0" srcdoc="'.htmlspecialchars($inner).'"></iframe>', new RenderingContext(300, 200));
        $child = $session->getEmbeddedSession($session->getDocument()->querySelector('iframe'));
        $root = $child->getDocument()->documentElement;
        $geometry = $child->render()->getScrollGeometry($root);
        self::assertNotNull($geometry);
        self::assertSame(40.0, $geometry->port->height);
        self::assertGreaterThan(0, $geometry->maxY);
        $child->scrollIntoView($child->getDocument()->getElementById('last'));
        self::assertGreaterThan(0, $child->render()->getScrollGeometry($root)->offsetY);
        self::assertNull($session->render()->getScrollGeometry($session->getDocument()->documentElement));
    }

    public function testSourceReplacementDiscardsOldChildBeforeCheckingItsMutations()
    {
        $session = new HtmlRenderer(1000)->createSession('<iframe srcdoc="&lt;p&gt;Old&lt;/p&gt;"></iframe>', new RenderingContext(300, 200));
        $frame = $session->getDocument()->querySelector('iframe');
        $child = $session->getEmbeddedSession($frame);
        $child->getDocument()->querySelector('p')->textContent = str_repeat('x', 2000);
        $frame->setAttribute('srcdoc', '<p>New</p>');
        $session->render();
        self::assertNotSame($child, $session->getEmbeddedSession($frame));
        self::assertContains('New', array_column($session->render()->getDisplayList()->getOperations(), 'text'));
    }

    public function testDiscardedFrameSvgResourcesDoNotRetainItsDocument()
    {
        $source = '<svg width="10" height="10"><rect width="10" height="10"/></svg>';
        $session = new HtmlRenderer()->createSession('<iframe srcdoc="'.htmlspecialchars($source).'"></iframe>', new RenderingContext(300, 200));
        $frame = $session->getDocument()->querySelector('iframe');
        $child = $session->getEmbeddedSession($frame);
        $document = \WeakReference::create($child->getDocument()->querySelector('svg'));
        unset($child);
        $frame->setAttribute('srcdoc', '<p>Replacement</p>');
        $session->render();
        gc_collect_cycles();
        self::assertNull($document->get());
    }

    public function testRetainedOrphanSessionsCannotExhaustTheirFormerParentsSvgSlots()
    {
        $source = '<svg width="10" height="10"><rect width="10" height="10"/></svg>';
        $session = new HtmlRenderer()->createSession('<iframe srcdoc="'.htmlspecialchars($source).'"></iframe>', new RenderingContext(300, 200));
        $frame = $session->getDocument()->querySelector('iframe');
        for ($version = 0; $version < 260; ++$version) {
            $orphan = $session->getEmbeddedSession($frame);
            $frame->setAttribute('srcdoc', $source.'<!--'.$version.'-->');
            $current = $session->render();
            $orphan->render();
        }
        self::assertContains('svg', array_column($current->getDisplayList()->getOperations(), 'type'));
        self::assertSame($current, $session->render());
        self::assertContains('svg', array_column($orphan->render()->getDisplayList()->getOperations(), 'type'));
    }

    public function testCachedSessionInspectionDoesNotPreflightEveryChildFrame()
    {
        $session = new HtmlRenderer()->createSession('<iframe srcdoc="&lt;p&gt;Before&lt;/p&gt;"></iframe>', new RenderingContext(300, 200));
        $frame = $session->getDocument()->querySelector('iframe');
        $child = $session->getEmbeddedSession($frame);
        $before = $session->render();
        $invalidations = 0;
        $session->onInvalidation(static function () use (&$invalidations): void { ++$invalidations; });
        $child->getDocument()->querySelector('p')->textContent = 'After';
        self::assertSame($child, $session->getEmbeddedSession($frame));
        self::assertSame(0, $invalidations);
        self::assertNotSame($before, $session->render());
        self::assertSame(1, $invalidations);
    }

    public function testRemovedAndAdoptedSessionInspectionPrunesItsNativeResources()
    {
        foreach ([false, true] as $adopt) {
            $session = new HtmlRenderer()->createSession('<iframe srcdoc="&lt;svg width=10 height=10&gt;&lt;rect width=10 height=10/&gt;&lt;/svg&gt;"></iframe>', new RenderingContext(300, 200));
            $frame = $session->getDocument()->querySelector('iframe');
            $child = $session->getEmbeddedSession($frame);
            $svg = \WeakReference::create($child->getDocument()->querySelector('svg'));
            unset($child);
            if ($adopt) {
                $other = \Dom\HTMLDocument::createFromString('<!doctype html><body>Other</body>');
                $other->body->appendChild($other->adoptNode($frame));
            } else {
                $frame->remove();
            }
            self::assertNull($session->getEmbeddedSession($frame));
            gc_collect_cycles();
            self::assertNull($svg->get());
        }
    }
}
