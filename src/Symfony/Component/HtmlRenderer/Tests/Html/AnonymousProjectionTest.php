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
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Html\BoxBuilder;
use Symfony\Component\HtmlRenderer\Resource\ResourceCollection;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

class AnonymousProjectionTest extends TestCase
{
    public function testAnonymousGroupsKeepSemanticTopologyAndNodeIdentity()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div>A<span>B</span><input>C<strong>D</strong><p>block</p>E</div>');
        $parent = $document->querySelector('div');
        [$a, $span, $input, $c, $strong, $p, $e] = iterator_to_array($parent->childNodes, false);
        $before = $document->saveHtml();
        $groups = new \SplObjectStorage();
        $groups[$parent] = [['style' => ['display' => 'flex', 'flex-wrap' => 'wrap'], 'children' => [
            ['style' => ['display' => 'block'], 'children' => [$a, $span]],
            $input,
            ['style' => ['display' => 'block'], 'children' => [$c, $strong]],
        ]], $p, ['style' => ['display' => 'block'], 'children' => [$e]]];
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 400, 200);
        $builder = new BoxBuilder($styles, $resolver, new ResourceCollection(new ResourceLoader()), new \SplObjectStorage(), null, $groups);
        $box = $builder->build($parent);
        self::assertCount(3, $box->children);
        self::assertNull($box->children[0]->node);
        self::assertSame('flex', $box->children[0]->display);
        self::assertSame($span, $box->children[0]->children[0]->children[1]->node);
        self::assertSame($input, $box->children[0]->children[1]->node);
        self::assertSame($p, $box->children[1]->node);
        self::assertSame($before, $document->saveHtml());
        self::assertSame([$span, $input, $strong, $p], array_values(array_filter(iterator_to_array($parent->childNodes, false), static fn ($child) => $child instanceof \Dom\Element)));
        self::assertCount(3, $builder->build($parent)->children);
    }

    public function testExplicitControlChildrenReplaceBuiltInContentWithoutChangingSemanticDom()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>select::before{content:"before"}</style><select><option selected>Choice</option></select>');
        $select = $document->querySelector('select');
        $option = $select->firstElementChild;
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 400, 200);
        $resources = new ResourceCollection(new ResourceLoader());
        $normal = new BoxBuilder($styles, $resolver, $resources, new \SplObjectStorage())->build($select);
        self::assertSame('Choice', $normal->children[1]->text);
        self::assertSame('1px', $normal->style['border-width']);
        $styles[$option] = ['display' => 'block'];
        $groups = new \SplObjectStorage();
        $groups[$select] = [$option];
        $projected = new BoxBuilder($styles, $resolver, $resources, new \SplObjectStorage(), null, $groups)->build($select);
        self::assertSame($select, $projected->node);
        self::assertSame('before', $projected->children[0]->pseudoElement);
        self::assertSame($option, $projected->children[1]->node);
        self::assertArrayNotHasKey('border-width', $projected->style);
        $groups[$select] = [];
        $empty = new BoxBuilder($styles, $resolver, $resources, new \SplObjectStorage(), null, $groups)->build($select);
        self::assertCount(1, $empty->children);
        self::assertSame('before', $empty->children[0]->pseudoElement);
        self::assertSame($option, $select->firstElementChild);
    }

    public function testExplicitSvgContentReplacesItsImageWithoutChangingTheDocument()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><svg aria-label="logo"></svg>');
        $svg = $document->querySelector('svg');
        $text = $document->createTextNode('[logo]');
        $svg->appendChild($text);
        $before = $document->saveHtml();
        $resolver = new StyleResolver();
        $styles = $resolver->resolve($document, 400, 200);
        $resources = new ResourceCollection(new ResourceLoader());
        $normal = new BoxBuilder($styles, $resolver, $resources, new \SplObjectStorage())->build($svg);
        self::assertNotNull($normal->image);
        $groups = new \SplObjectStorage();
        $groups[$svg] = [$text];
        $projected = new BoxBuilder($styles, $resolver, $resources, new \SplObjectStorage(), null, $groups)->build($svg);
        self::assertNull($projected->image);
        self::assertCount(1, $projected->children);
        self::assertSame($text, $projected->children[0]->node);
        self::assertSame('[logo]', $projected->children[0]->text);
        self::assertSame($before, $document->saveHtml());
        self::assertSame($svg, $text->parentNode);
        self::assertNotNull(new BoxBuilder($styles, $resolver, $resources, new \SplObjectStorage())->build($svg)->image);
    }

    public function testDuplicateSemanticReferencesAreRejected()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div><span>A</span></div>');
        $parent = $document->querySelector('div');
        $child = $parent->firstElementChild;
        $groups = new \SplObjectStorage();
        $groups[$parent] = [['style' => [], 'children' => [$child, $child]]];
        $resolver = new StyleResolver();
        $this->expectException(\InvalidArgumentException::class);
        new BoxBuilder($resolver->resolve($document, 400, 200), $resolver, new ResourceCollection(new ResourceLoader()), new \SplObjectStorage(), null, $groups)->build($parent);
    }

    public function testCyclicDescriptorsAreBounded()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><div></div>');
        $parent = $document->querySelector('div');
        $cycle = ['style' => [], 'children' => []];
        $cycle['children'][] = &$cycle;
        $groups = new \SplObjectStorage();
        $groups[$parent] = [$cycle];
        $resolver = new StyleResolver();
        $this->expectException(\InvalidArgumentException::class);
        new BoxBuilder($resolver->resolve($document, 400, 200), $resolver, new ResourceCollection(new ResourceLoader()), new \SplObjectStorage(), null, $groups)->build($parent);
    }
}
