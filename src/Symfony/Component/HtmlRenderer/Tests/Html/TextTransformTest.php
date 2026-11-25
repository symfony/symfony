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
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\Text\TextTransform;

class TextTransformTest extends TestCase
{
    public function testDocumentTransformInheritsWithLocalOverrideAndKeepsNativeText()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><p style="text-transform:uppercase">hello <span style="text-transform:none">MiXeD</span> ß</p>');
        $source = $document->saveHtml();
        $result = new HtmlRenderer()->render($document, new RenderingContext(800, 200));
        $texts = array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation) => 'text' === $operation['type']), 'text');
        self::assertSame('HELLO MiXeD SS', implode('', $texts));
        self::assertSame($source, $document->saveHtml());
    }

    public function testSharedKeywordOrderAndUnicodeConversions()
    {
        self::assertSame('ＡＢＣ', TextTransform::apply('abc', TextTransform::keywords('full-width uppercase')));
        self::assertSame('あツ', TextTransform::apply('ぁッ', TextTransform::keywords('full-size-kana')));
        self::assertSame('Hello World', TextTransform::apply('hello world', TextTransform::keywords('capitalize')));
        self::assertNull(TextTransform::keywords('uppercase lowercase'));
    }
}
