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
use Symfony\Component\HtmlRenderer\Css\ContainerQuery;
use Symfony\Component\HtmlRenderer\Css\CssParser;

class ContainerQueryTest extends TestCase
{
    public function testQueryLengthsSeparateViewportContainerAndRootFontMetrics()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@container(width > 10vw){p{color:red}}@container(width > 6em){p{padding:2px}}@container(width > 4rem){p{margin:3px}}@container(width > 11ch){p{top:1px}}@container(width > 20vw){p{left:1px}}</style><main><p>Text</p></main>');
        $map = new \SplObjectStorage();
        $map[$document->documentElement] = ['style' => ['font-size' => '30px', 'line-height' => '36px'], 'width' => 800.0, 'height' => 600.0];
        $map[$document->querySelector('main')] = ['style' => ['container-type' => 'inline-size', 'font-size' => '20px', 'line-height' => '24px'], 'width' => 100.0, 'height' => 20.0];
        $resolver = new \Symfony\Component\HtmlRenderer\Css\StyleResolver();
        $values = $resolver->resolve($document, 800, 600, [], null, 0, $map)[$document->querySelector('p')];
        self::assertSame('red', $values['color']);
        self::assertArrayNotHasKey('padding-top', $values);
        self::assertArrayNotHasKey('margin-left', $values);
        self::assertArrayNotHasKey('top', $values);
        self::assertArrayNotHasKey('left', $values);
    }

    public function testResolverUsesExplicitNearestEligibleContentMetrics()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@container card (width > 300px){p{color:red}}@container (height > 100px){p{padding:2px}}@container style(--theme:dark){p{margin:3px}}</style><main><section><p>Text</p></section></main>');
        $outer = $document->querySelector('main');
        $inner = $document->querySelector('section');
        $paragraph = $document->querySelector('p');
        $map = new \SplObjectStorage();
        $map[$outer] = ['style' => ['container-type' => 'size', 'container-name' => 'card'], 'width' => 500.0, 'height' => 200.0];
        $map[$inner] = ['style' => ['container-type' => 'inline-size', '--theme' => 'dark'], 'width' => 100.0, 'height' => 20.0];
        $resolver = new \Symfony\Component\HtmlRenderer\Css\StyleResolver();
        self::assertSame('#000000', $resolver->resolve($document, 800, 600)[$paragraph]['color']);
        self::assertTrue($resolver->hasContainerQueries());
        $values = $resolver->resolve($document, 800, 600, [], null, 0, $map)[$paragraph];
        self::assertSame('red', $values['color']);
        self::assertSame('2px', $values['padding-top']);
        self::assertSame('3px', $values['margin-left']);
        $map[$outer] = ['style' => ['container-type' => 'size', 'container-name' => 'card'], 'width' => \INF, 'height' => 200.0];
        self::assertSame('#000000', $resolver->resolve($document, 800, 600, [], null, 0, $map)[$paragraph]['color']);
    }

    public function testScrollQueryUsesNamedScrollContainerState()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>@container port scroll-state(scrollable:bottom){p{color:red}}</style><main><p>Text</p></main>');
        $map = new \SplObjectStorage();
        $map[$document->querySelector('main')] = ['style' => ['container' => 'port / scroll-state'], 'width' => 100.0, 'height' => 20.0, 'scroll' => ['scrollable' => ['bottom']]];
        $values = new \Symfony\Component\HtmlRenderer\Css\StyleResolver()->resolve($document, 800, 600, [], null, 0, $map)[$document->querySelector('p')];
        self::assertSame('red', $values['color']);
    }

    public function testSizeStyleAndScrollGrammarRemainIndependentOfDevices()
    {
        $query = ContainerQuery::parse('sidebar (width >= 300px) and style(--theme: dark)');
        self::assertSame('sidebar', $query->name);
        self::assertTrue($query->matchesStyle(['--theme' => 'dark']));
        self::assertFalse($query->matchesStyle(['--theme' => 'light']));
        self::assertTrue($query->condition->evaluate(static fn (string $feature, string $operator, ?string $value): bool => 'width' === $feature && '>=' === $operator && '300px' === $value));
        $scroll = ContainerQuery::parse('scroll-state(scrollable: bottom)');
        self::assertTrue($scroll->matchesScrollState(['scrollable' => ['bottom']]));
        self::assertFalse($scroll->matchesScrollState(['scrollable' => []]));
    }

    public function testParserRetainsQueriesWithoutApplyingUnknownMetrics()
    {
        $rules = new CssParser()->parse('@container (width > 300px){p{color:red}}');
        self::assertCount(1, $rules);
        self::assertInstanceOf(ContainerQuery::class, $rules[0]->containers[0]);
    }
}
