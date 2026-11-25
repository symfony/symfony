<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Css\CascadeResolver;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Css\CssParser;
use Symfony\Component\HtmlRenderer\Css\ParsedStylesheet;

class CoreStylesheetAdapterTest extends TestCase
{
    public function testCoreMediaQueriesCanBeAdaptedWithoutRequiringBridgeQuerySubclasses()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p>Target</p>');
        $stylesheet = new ParsedStylesheet(new CssParser()->parse('p{color:blue}@media(min-width:40){p{color:red}}'));
        $resolver = new CascadeResolver();
        $narrow = $resolver->cascadeMirror($document, [], $stylesheet, new MediaContext(20, 24), [], []);
        $wide = $resolver->cascadeMirror($document, [], $stylesheet, new MediaContext(80, 24), [], []);
        self::assertSame('blue', $narrow[$document->querySelector('p')]['computed']['color']);
        self::assertSame('red', $wide[$document->querySelector('p')]['computed']['color']);
    }
}
