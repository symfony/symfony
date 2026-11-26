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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Animation\Timeline;
use Symfony\Component\HtmlRenderer\Css\StyleResolver;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\MonospaceTextMeasurer;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;

final class PreparedWinnerLifetimeTest extends TestCase
{
    #[DataProvider('provideBorders')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRepeatedPreparedCascadesRetainTheirWinnerMetadata(string $border)
    {
        $html = '<!doctype html><style>p::target-text{color:Mark;background-color:MarkText}</style>';
        for ($i = 0; $i < 1000; ++$i) {
            $html .= '<p style="--tone:red;--gap:8px;--weight:700;--surface:#123456;--edge:blue;--radius:4px;--alpha:1;--count:2;color:var(--tone);padding:var(--gap);'.$border.'">Text '.$i.'</p>';
        }
        $document = \Dom\HTMLDocument::createFromString($html);
        $source = $document->saveHtml();
        $resolver = new StyleResolver(new ResourceLoader(), new FontStyle(), new MonospaceTextMeasurer(), [], '*{box-sizing:border-box}');
        $timeline = new Timeline();
        $first = $document->querySelector('p');
        $last = $document->querySelector('p:last-child');
        for ($round = 0; $round < 7; ++$round) {
            $states = [];
            foreach ($document->querySelectorAll('p') as $element) {
                $states[] = [$element, $round % 2 ? ['hover'] : []];
            }
            $trial = clone $timeline;
            $styles = $resolver->resolve($document, 800, 600, $states, $trial, (float) $round, null, null, null, [], null, true);
            $timeline->adopt($trial);
            foreach ([$first, $last] as $element) {
                self::assertSame('8px', $styles[$element]['padding-left']);
                self::assertSame('border-box', $styles[$element]['box-sizing']);
                self::assertSame('red', $styles[$element]['color']);
                $record = $resolver->getCascadeRecord($element);
                self::assertSame('user-agent', $record['declarations']['box-sizing']['origin']);
                self::assertSame('author', $record['declarations']['padding-left']['origin']);
                self::assertSame($styles[$element], $record['computed']);
                if ('' !== $border) {
                    self::assertSame('solid', $styles[$element]['border-left-style']);
                    self::assertSame('author', $record['declarations']['border-left-color']['origin']);
                }
            }
            $resolver->releaseComputedStyles();
            $trial = clone $timeline;
            $prepared = $resolver->resolvePrepared($trial, $round + .5);
            $timeline->adopt($trial);
            self::assertSame($styles[$first], $prepared[$first]);
            self::assertSame($styles[$last], $prepared[$last]);
            $resolver->releaseComputedStyles();
        }
        self::assertSame($source, $document->saveHtml());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testReplacingPreparedDocumentReleasesItsOwnership()
    {
        $resolver = new StyleResolver(new ResourceLoader(), new FontStyle(), new MonospaceTextMeasurer(), [], '*{box-sizing:border-box}');
        $prepare = static function () use ($resolver): \WeakReference {
            $document = \Dom\HTMLDocument::createFromString('<!doctype html><style>p{border:1px solid red}@starting-style{p{border-color:blue}}</style><p>Old</p>');
            $element = $document->querySelector('p');
            $timeline = new Timeline();
            $resolver->resolve($document, 100, 100, [], $timeline, 0, null, null, null, [], null, true);
            self::assertSame('blue', $resolver->getCascadeRecord($element)['starting-style']['computed']['border-left-color']);
            $resolver->releaseComputedStyles();

            return \WeakReference::create($document);
        };
        $previous = $prepare();
        self::assertNotNull($previous->get());
        $replacement = \Dom\HTMLDocument::createFromString('<!doctype html><p>New</p>');
        $resolver->resolve($replacement, 100, 100, [], new Timeline(), 0, null, null, null, [], null, true);
        $resolver->releaseComputedStyles();
        self::assertNull($previous->get());
    }

    public static function provideBorders(): iterable
    {
        yield 'user-agent metadata' => [''];
        yield 'user-agent and explicit border metadata' => ['border:1px solid red'];
    }
}
