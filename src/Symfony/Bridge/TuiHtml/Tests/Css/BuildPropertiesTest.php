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
use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;

/**
 * The properties whose rules HtmlView::setInnerHtml() builds the elements they match again for: every one the builder
 * reads from the rules, through the checks of the style sheet it asks before building an element.
 */
final class BuildPropertiesTest extends TestCase
{
    // A border declared by a rule, not drawn in every media: one of its widths in a media draws it
    private const BASE_CSS = '.boxed { display: inline-block; border-style: solid; border-width: 0 }';
    private const VALUES = ['block', 'inline', 'inline-block', 'inline-flex', 'inline-grid', 'flex', 'grid', 'table', 'inline-table', 'table-cell', 'list-item', 'flow-root', 'contents', 'none', 'inherit', 'absolute', 'fixed', 'left', 'right', 'inline-start', 'column', 'auto', 'scroll', 'hidden', '10ch', '2', '1px solid', 'solid', 'thick', 'rect(0 0 0 0)', 'inset(1px)', 'nearest', '"x"'];

    /** @var array{string, list<string>, list<mixed>}|null The elements, the checks, and their answers without the rules of a property */
    private static ?array $base = null;

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideCandidates(): iterable
    {
        foreach (self::candidates() as $property) {
            yield $property => [$property];
        }
    }

    /**
     * One case a property, each drawing a dot: all of them in one test ran for minutes without output on slow machines.
     */
    #[DataProvider('provideCandidates')]
    public function testEveryPropertyTheBuilderReadsIsABuildProperty(string $property)
    {
        if (!self::reads($property)) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->assertArrayHasKey($property, (new \ReflectionClassConstant(CssStyleSheet::class, 'BUILD_PROPERTIES'))->getValue(), 'Add it to CssStyleSheet::BUILD_PROPERTIES.');
    }

    public function testAPropertyTheBuilderReadsIsFound()
    {
        $this->assertGreaterThan(15, \count(self::base()[1]), 'The checks of the style sheet are found.');
        $this->assertTrue(self::reads('display'));
        $this->assertFalse(self::reads('color'));
    }

    /**
     * Whether rules of the property, in every media and state, in some media, in some state, change what a check answers.
     */
    private static function reads(string $property): bool
    {
        [$html, $checks, $before] = self::base();
        $css = '';
        foreach (self::VALUES as $i => $value) {
            $css .= '.v'.$i.' { '.$property.': '.$value.' } @media (min-width: 1px) { .v'.$i.' { '.$property.': '.$value.' } } .v'.$i.':hover { '.$property.': '.$value.' }';
        }

        return $before !== self::answers(CssStyleSheet::fromString(self::BASE_CSS.$css), $html, $checks);
    }

    /**
     * @return array{string, list<string>, list<mixed>}
     */
    private static function base(): array
    {
        if (null !== self::$base) {
            return self::$base;
        }
        $checks = self::checks();
        $html = '';
        foreach (self::VALUES as $i => $value) {
            // Checks of several properties too, like a border of an inline block, or its width once a border is drawn
            $html .= '<div class="v'.$i.'"><span class="v'.$i.'">x</span> <p class="v'.$i.'">y</p><span class="v'.$i.'" style="display: inline-block">z</span><span class="v'.$i.'" style="display: inline-flex">w</span><b class="v'.$i.'" style="position: absolute">v</b><span class="v'.$i.' boxed">u</span><span class="v'.$i.'" style="display: inline-block; border: 1px solid">t</span></div>';
        }

        return self::$base = [$html, $checks, self::answers(CssStyleSheet::fromString(self::BASE_CSS), $html, $checks)];
    }

    /**
     * The public checks of an element the style sheet answers from its rules, like mayBeBlock().
     *
     * @return list<string>
     */
    private static function checks(): array
    {
        $checks = [];
        foreach ((new \ReflectionClass(CssStyleSheet::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $parameters = $method->getParameters();
            if (preg_match('/^(?:may|stays|always)[A-Z]/', $method->getName()) && 1 <= \count($parameters) && 1 === $method->getNumberOfRequiredParameters() && \Dom\Element::class === (string) $parameters[0]->getType()) {
                $checks[] = $method->getName();
            }
        }

        return $checks;
    }

    /**
     * The names of properties the style sheet and the builder name in their code.
     *
     * @return list<string>
     */
    private static function candidates(): array
    {
        // Retain every original candidate while shared helpers migrate out of these three files.
        $names = json_decode(file_get_contents(__DIR__.'/../Fixtures/build-properties-original-candidates.json'), true, 512, \JSON_THROW_ON_ERROR);
        foreach (['Css/CssStyleSheet.php', 'Css/DeclarationMapper.php', 'Html/HtmlBuilder.php'] as $file) {
            preg_match_all("/'([a-z]+(?:-[a-z]+)*)'/", (string) file_get_contents(\dirname(__DIR__, 2).'/'.$file), $matches);
            array_push($names, ...$matches[1]);
        }

        return array_values(array_unique($names));
    }

    /**
     * @param list<string> $checks
     *
     * @return list<mixed>
     */
    private static function answers(CssStyleSheet $styleSheet, string $html, array $checks): array
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', \LIBXML_NOERROR);
        $builder = new HtmlBuilder($styleSheet);
        $answers = [];
        foreach ($document->body?->querySelectorAll('*') ?? [] as $element) {
            foreach ($checks as $check) {
                $answers[] = $styleSheet->{$check}($element);
            }
            $answers[] = $builder->placement($element);
        }

        return $answers;
    }
}
