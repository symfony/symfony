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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Css\CascadeResolver;
use Symfony\Bridge\TuiHtml\Css\CssParser;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Component\HtmlRenderer\Css\ParsedStylesheet;

final class CapturedProjectionTest extends TestCase
{
    #[DataProvider('provideAnimatedProperties')]
    public function testCapturedAnimatedValuesRemainDistinctForEachOwner(string $target, string $property)
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><section><p data-tui-oid="1">First</p><p data-tui-oid="2">Second</p></section>');
        $source = $document->saveHtml();
        [$first, $second] = iterator_to_array($document->querySelectorAll('p'));
        $parser = new CssParser();
        $rules = $parser->parse('@keyframes phase{from{'.$property.':1px}to{'.$property.':3px}}'.$target.'{animation:phase 1s linear both}');
        $bundle = new ParsedStylesheet($rules, $parser->takeRegisteredProperties(), $parser->takeKeyframes(), $parser->takeCounterStyles(), $parser->takeFunctions(), $parser->takePositionTries());
        $captures = 0;
        $capture = static function (\SplObjectStorage $styles) use ($first, $second, $property, &$captures): void {
            ++$captures;
            $styles[$first] = array_replace($styles[$first], [$property => '20px']);
            $styles[$second] = array_replace($styles[$second], [$property => '30px']);
        };
        $records = new CascadeResolver()->cascadeMirror($document, [1 => $first, 2 => $second], $bundle, new MediaContext(), [], [], [], null, null, null, $capture, null, null, 0.0);
        self::assertSame(1, $captures);
        self::assertSame('20px', $records[$first]['computed'][$property]);
        self::assertSame('30px', $records[$second]['computed'][$property]);
        self::assertSame('20px', $records[$first]['raw'][$property]);
        self::assertSame('30px', $records[$second]['raw'][$property]);
        self::assertSame($source, $document->saveHtml());
    }

    public static function provideAnimatedProperties(): iterable
    {
        yield 'inherited animation on undeclared owners' => ['section', 'letter-spacing'];
        yield 'animation declared on each owner' => ['p', 'padding-left'];
    }
}
