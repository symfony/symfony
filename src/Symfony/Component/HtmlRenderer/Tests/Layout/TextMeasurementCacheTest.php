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
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\Layout\Box;
use Symfony\Component\HtmlRenderer\Layout\LayoutEngine;

final class TextMeasurementCacheTest extends TestCase
{
    public function testEveryFontFieldParticipatesAndIdenticalMeasurementsAreReused()
    {
        $measurer = $this->measurer();
        $styles = [[], ['font-family' => 'B'], ['font-size' => '11px'], ['line-height' => '12px'], ['font-weight' => '700'], ['direction' => 'rtl'], ['font-style' => 'italic']];
        $children = [];
        foreach ($styles as $style) {
            $children[] = new Box(null, 'block', $style, [], 'x');
            $children[] = new Box(null, 'block', $style, [], 'x');
        }
        (new LayoutEngine($measurer))->layout($this->box($children), 200, 200);
        self::assertCount(7, $measurer->seen['x']);
        self::assertSame([1], array_values(array_unique($measurer->seen['x'])));
    }

    public function testFloatKeysRetainExactBinaryValuesWithLowSerializationPrecision()
    {
        $precision = \ini_get('serialize_precision');
        ini_set('serialize_precision', '3');
        try {
            $measurer = $this->measurer();
            $root = $this->box([new Box(null, 'block', ['font-size' => '1.0001px'], [], 'x'), new Box(null, 'block', ['font-size' => '1.0002px'], [], 'x')]);
            $layout = (new LayoutEngine($measurer))->layout($root, 200, 200);
            self::assertCount(2, $measurer->seen['x']);
            self::assertNotSame($layout->children[0]->children[0]->bounds->width, $layout->children[1]->children[0]->bounds->width);
        } finally {
            ini_set('serialize_precision', $precision);
        }
    }

    public function testCacheStopsRetainingNewEntriesAtBudgetAndBypassesLongInputs()
    {
        $measurer = $this->measurer();
        $children = [];
        for ($index = 0; $index < 1030; ++$index) {
            $children[] = new Box(null, 'block', [], [], 'word'.$index);
        }
        (new LayoutEngine($measurer))->layout($this->box($children), 200, 20000);
        self::assertSame(1, array_sum($measurer->seen['word0']));
        self::assertGreaterThan(1, array_sum($measurer->seen['word1029']));
        self::assertSame(1024, array_sum(array_map(static fn (array $counts): int => \count(array_filter($counts, static fn (int $count): bool => 1 === $count)), $measurer->seen)));

        foreach ([['text' => str_repeat('a', 257), 'style' => []], ['text' => 'x', 'style' => ['font-family' => str_repeat('f', 257)]], ['text' => 'x', 'style' => ['direction' => 'unknown']]] as $case) {
            $measurer = $this->measurer();
            $root = $this->box([new Box(null, 'block', $case['style'], [], $case['text']), new Box(null, 'block', $case['style'], [], $case['text'])]);
            (new LayoutEngine($measurer))->layout($root, 2000, 200);
            self::assertGreaterThan(1, array_sum($measurer->seen[$case['text']]));
        }
    }

    public function testMaximumSupportedTextAndFamilyLengthsAreCacheable()
    {
        $measurer = $this->measurer();
        $text = str_repeat('a', 256);
        $style = ['font-family' => str_repeat('f', 256)];
        $root = $this->box([new Box(null, 'block', $style, [], $text), new Box(null, 'block', $style, [], $text)]);
        (new LayoutEngine($measurer))->layout($root, 3000, 200);
        self::assertSame(1, array_sum($measurer->seen[$text]));
    }

    public function testEachLayoutRefreshesMetricsAndExceptionsDoNotPoisonLaterLayouts()
    {
        $measurer = $this->measurer();
        $engine = new LayoutEngine($measurer);
        $box = $this->box([new Box(null, 'block', [], [], 'x')]);
        $first = $engine->layout($box, 200, 200);
        $measurer->extra = 7.0;
        $second = $engine->layout($box, 200, 200);
        self::assertSame(7.0, $second->children[0]->children[0]->bounds->width - $first->children[0]->children[0]->bounds->width);
        $measurer->fail = true;
        try {
            $engine->layout($box, 200, 200);
            self::fail('The measurement exception must propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Measurement failed.', $exception->getMessage());
        }
        $measurer->fail = false;
        self::assertSame($second->bounds->height, $engine->layout($box, 200, 200)->bounds->height);
    }

    private function box(array $children): Box
    {
        return new Box(null, 'block', ['font-family' => 'A', 'font-size' => '10px', 'line-height' => '10px', 'font-weight' => '400', 'direction' => 'ltr'], $children);
    }

    private function measurer(): object
    {
        return new class implements TextMeasurerInterface {
            public array $seen = [];
            public float $extra = 0.0;
            public bool $fail = false;

            public function measure(string $text, FontStyle $font): TextMetrics
            {
                if ($this->fail) {
                    throw new \RuntimeException('Measurement failed.');
                }
                $key = serialize([$font->family, pack('dd', $font->size, $font->lineHeight), $font->weight, $font->direction, $font->style]);
                $this->seen[$text][$key] = ($this->seen[$text][$key] ?? 0) + 1;

                return new TextMetrics(\strlen($text) * $font->size + $this->extra, $font->size * 0.8, $font->size * 0.2);
            }
        };
    }
}
