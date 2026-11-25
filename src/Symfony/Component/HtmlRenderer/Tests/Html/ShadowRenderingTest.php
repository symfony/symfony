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
use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Html\ShadowRoots;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class ShadowRenderingTest extends TestCase
{
    public function testAnAnchorMappingMustPreserveItsSourceElementIdentity(): void
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p></p><i></i>');
        $element = $document->querySelector('p');
        $anchor = $document->querySelector('i');
        $owners = new \SplObjectStorage();
        $owners[$element] = $element;
        $owners[$anchor] = $document->createTextNode('not an element');
        $fragment = new \Symfony\Component\HtmlRenderer\Layout\Fragment($element, new \Symfony\Component\HtmlRenderer\Geometry\Rect(0, 0, 1, 1), [], null, [], null, null, [], null, null, null, null, null, $anchor);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A composed anchor must preserve its source element identity.');
        \Symfony\Component\HtmlRenderer\Html\ShadowRendering::fragment($fragment, $owners);
    }

    public function testScopedStylesPaintAndKeepSourceIdentities()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>.note{color:green}user-card::part(name){color:red}</style><user-card><template shadowrootmode="open"><style>:host{display:block}h3{color:blue}::slotted(.note){font-style:italic}.note{color:orange}</style><h3 part="name"><slot name="name">Anonymous</slot></h3><p id="inside" class="note">Inside</p></template><span class="note" slot="name">Ada</span></user-card>');
        $source = $document->saveHtml();
        $light = $document->querySelector('span');
        $manager = new ShadowRoots();
        $view = $manager->createView($document);
        $inside = $view['owners'][$view['document']->querySelector('#inside')];
        $result = HtmlRenderer::compute($document, new RenderingContext(400, 300), [], null, 0, null, null, null, $view);
        self::assertSame($document, $result->getDocument());
        self::assertSame($source, $document->saveHtml());
        self::assertNull($document->getElementById('inside'));
        self::assertSame($light, $document->querySelector('span'));
        self::assertSame('green', $result->getComputedStyle($light)['color']);
        self::assertSame('italic', $result->getComputedStyle($light)['font-style']);
        self::assertSame('orange', $result->getComputedStyle($inside)['color']);
        self::assertNotEmpty($result->getBounds($inside));
        self::assertNotEmpty($result->getBounds($light));
        $text = implode(' ', array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']), 'text'));
        self::assertStringContainsString('Ada', $text);
        self::assertStringContainsString('Inside', $text);
        self::assertStringNotContainsString('Anonymous', $text);
    }

    public function testContainerQueriesKeepVisualKeysUntilLayoutStabilizes()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><user-card><template shadowrootmode="open"><style>:host{display:block}.container{display:block;container-type:inline-size;width:80px}.target{color:red}@container(min-width:60px){.target{color:blue}}</style><div class="container"><span id="target" class="target">Target</span></div></template></user-card>');
        $view = (new ShadowRoots())->createView($document);
        $target = $view['owners'][$view['document']->querySelector('#target')];
        $result = HtmlRenderer::compute($document, new RenderingContext(400, 300), [], null, 0, null, null, null, $view);
        self::assertSame('blue', $result->getComputedStyle($target)['color']);
        self::assertNotEmpty($result->getBounds($target));
    }

    public function testEncapsulationContextPrecedesSpecificityAndReversesForImportantRules()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>#card{color:green;background-color:blue!important}</style><user-card id="card" class="featured"><template shadowrootmode="open"><style>:host(#card.featured){color:red}:host{display:block;background-color:red!important}</style><p id="text">Text</p></template></user-card>');
        $view = (new ShadowRoots())->createView($document);
        $result = HtmlRenderer::compute($document, new RenderingContext(400, 300, null, null, null, null, [], 'p{font-weight:bold}'), [], null, 0, null, null, null, $view);
        $host = $document->getElementById('card');
        $inner = $view['owners'][$view['document']->querySelector('#text')];
        self::assertSame('green', $result->getComputedStyle($host)['color']);
        self::assertSame('red', $result->getComputedStyle($host)['background-color']);
        self::assertSame('700', $result->getComputedStyle($inner)['font-weight']);
    }

    public function testNestedExportedPartsKeepNativeSourceOwners()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>user-badge::part(dot){color:red}user-badge::part(text){color:green}</style><user-badge><template shadowrootmode="open"><status-dot exportparts="dot"><template shadowrootmode="open"><span id="dot" part="dot">Dot</span><span id="text" part="text">Private</span></template></status-dot></template></user-badge>');
        $view = (new ShadowRoots())->createView($document);
        $dot = $view['owners'][$view['document']->querySelector('#dot')];
        $text = $view['owners'][$view['document']->querySelector('#text')];
        $result = HtmlRenderer::compute($document, new RenderingContext(400, 300), [], null, 0, null, null, null, $view);
        self::assertSame('red', $result->getComputedStyle($dot)['color']);
        self::assertNotSame('green', $result->getComputedStyle($text)['color']);
        self::assertNotEmpty($result->getBounds($dot));
        self::assertFalse($dot->isConnected);
    }

    public function testAnimationRetainsSourceKeysAcrossMirrorReplacementAndRevokesInactiveRoots()
    {
        $document = \Dom\HTMLDocument::createFromString('<!doctype html><user-card><template shadowrootmode="open"><style>:host{display:block}@keyframes fade{from{opacity:0}to{opacity:1}}p{animation:fade 2s linear forwards}</style><p id="animated">Animated</p></template></user-card>');
        $manager = new ShadowRoots();
        $timeline = new Timeline();
        $resolver = new StyleResolver();
        $context = new RenderingContext(400, 300);
        $view = $manager->createView($document);
        $element = $view['owners'][$view['document']->querySelector('#animated')];
        self::assertFalse($element->isConnected);
        $first = HtmlRenderer::compute($document, $context, [], $timeline, 0, null, $resolver, null, $view);
        self::assertSame('0', $first->getComputedStyle($element)['opacity']);
        self::assertTrue($timeline->hasPendingFrames(0));
        $document->body->appendChild($document->createElement('aside'));
        $view = $manager->createView($document);
        self::assertSame($element, $view['owners'][$view['document']->querySelector('#animated')]);
        $second = HtmlRenderer::compute($document, $context, [], $timeline, 1, null, $resolver, null, $view);
        self::assertSame('0.5', $second->getComputedStyle($element)['opacity']);
        self::assertTrue($timeline->hasPendingFrames(1));
        $document->querySelector('user-card')->remove();
        $view = $manager->createView($document);
        HtmlRenderer::compute($document, $context, [], $timeline, 1.5, null, $resolver, null, $view);
        self::assertFalse($timeline->hasPendingFrames(1.5));
    }
}
