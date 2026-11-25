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
use Symfony\Component\HtmlRenderer\Css\MediaQuery;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

class MediaQueryTest extends TestCase
{
    public function testValueMappingPreservesLogicalStructureWithoutMutatingTheQuery()
    {
        $query = MediaQuery::parse('not ((width > 4) or (height > 3))');
        $mapped = $query->mapValues(static fn (string $name, string $operator, ?string $value): ?string => null === $value ? null : (string) ((float) $value * 10));
        $feature = static fn (string $name, string $operator, ?string $value): bool => MediaQuery::compare(20, $operator, null === $value ? null : (float) $value);
        self::assertFalse($query->evaluate($feature));
        self::assertTrue($mapped->evaluate($feature));
        self::assertSame($query->source, $mapped->source);
        self::assertTrue($mapped->usesBlockAxis());
    }

    public function testLogicalQueriesAndRangesUseFeatureEvaluator()
    {
        $query = MediaQuery::parse('((100px < width <= 500px) and (orientation: landscape)) or (prefers-reduced-motion: reduce)');
        $evaluate = static fn (string $feature, string $operator, ?string $value): bool => match ($feature) {
            'width' => MediaQuery::compare(300, $operator, (float) $value),
            'orientation' => 'landscape' === $value,
            default => false,
        };
        self::assertTrue($query->evaluate($evaluate));
        self::assertFalse(MediaQuery::parse('not ((width > 100px) and (width < 500px))')->evaluate($evaluate));
        self::assertTrue(MediaQuery::parse('(--wide)', ['--wide' => '(width >= 200px)'])->evaluate($evaluate));
    }

    public function testViewportQueriesUseDeviceFontMeasurementAndExplicitPreferences()
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><style>p { width: 1px } @media (max-width: 40ch) and (prefers-reduced-motion: reduce) { p { width: 2.5px } }</style><p>Text</p>');
        $measure = new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                return new TextMetrics(9 * \strlen($text), 8, 2);
            }
        };
        $style = new StyleResolver(new ResourceLoader(), new FontStyle(), $measure, ['prefers-reduced-motion' => 'reduce'])->resolve($document, 350, 200);
        self::assertSame('2.5px', $style[$document->querySelector('p')]['width']);
        $style = new StyleResolver(new ResourceLoader(), new FontStyle(), $measure, ['prefers-reduced-motion' => 'reduce'])->resolve($document, 370, 200);
        self::assertSame('1px', $style[$document->querySelector('p')]['width']);
    }
}
