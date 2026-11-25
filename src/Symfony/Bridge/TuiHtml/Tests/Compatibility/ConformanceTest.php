<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Compatibility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Compatibility\CompatibilityReport;
use Symfony\Bridge\TuiHtml\Compatibility\SupportLevel;
use Symfony\Bridge\TuiHtml\Style\Density;

/**
 * Renders the fixtures of Fixtures/Compat and compares them to their snapshots.
 *
 * Each fixture starts with comments:
 *
 *     <!-- covers: css.properties.color css.types.color.rgb -->
 *     <!-- variant: dark-40 columns=40 scheme=dark -->
 *     <!-- requires: GdImage IntlChar -->
 *
 * and is rendered in the regular and condensed densities, plus each variant, unless a class it requires is missing
 * (options: columns, rows, scheme, density, contrast, forced-colors,
 * reduced-motion, dppx, pointer, colors, text-sizing, focus=#id, hover=#id, target=#id, visited=href,..., highlight=name:text_with_spaces,..., scroll=#id:rows, scroll-x=#id:columns,
 * click=row:col, keys=enter,down,f6...). Run with TUI_UPDATE_COMPAT=1
 * to write the snapshots, then review them.
 */
final class ConformanceTest extends TestCase
{
    private const DIR = __DIR__.'/../Fixtures/Compat';

    #[DataProvider('provideFixtures')]
    public function testFixture(string $fixture, string $variant, array $options)
    {
        $html = file_get_contents(self::DIR.'/'.$fixture.'.html');
        // The expected output needs every class the fixture names, like GD for images and IntlChar for visual RTL.
        preg_match_all('/<!--\s*requires:\s*(.*?)\s*-->/', $html, $requirements);
        foreach ($requirements[1] as $classes) {
            foreach (preg_split('/\s+/', trim($classes), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
                if (!class_exists($class)) {
                    $this->markTestSkipped(\sprintf('The %s class is needed.', $class));
                }
            }
        }
        $snapshot = self::DIR.'/'.$fixture.'.'.$variant.'.txt';
        // Bundled and external GD rasterize some shape edges differently: each reference stays exact.
        if (\defined('GD_BUNDLED') && \GD_BUNDLED && file_exists($bundled = self::DIR.'/'.$fixture.'.'.$variant.'.bundled-gd.txt')) {
            $snapshot = $bundled;
        }
        $actual = $this->render($html, $options);
        if (getenv('TUI_UPDATE_COMPAT')) {
            file_put_contents($snapshot, $actual);
            $this->addToAssertionCount(1);

            return;
        }
        $this->assertFileExists($snapshot, 'Run the tests with TUI_UPDATE_COMPAT=1 to write the snapshot.');
        if (str_contains($html, '<!-- gd-color-rounding -->')) {
            AnsiSnapshot::assertSameWithColorRounding(file_get_contents($snapshot), $actual);
        } else {
            $this->assertSame(file_get_contents($snapshot), $actual);
        }
    }

    public static function provideFixtures(): iterable
    {
        foreach (glob(self::DIR.'/*.html') as $file) {
            $fixture = basename($file, '.html');
            $html = file_get_contents($file);
            foreach (FixtureVariants::fromHtml($html) as [$variant, $options]) {
                yield $fixture.' '.$variant => [$fixture, $variant, $options];
            }
        }
    }

    public function testEverySupportedFeatureIsCovered()
    {
        $covered = [];
        foreach (glob(self::DIR.'/*.html') as $file) {
            if (preg_match('/<!--\s*covers:(.*?)-->/s', file_get_contents($file), $m)) {
                $covered += array_fill_keys(preg_split('/\s+/', trim($m[1])), true);
            }
        }
        $missing = [];
        foreach (CompatibilityReport::createDefault()->getFeatures() as $key => $support) {
            if (SupportLevel::Ignored !== $support->level && !isset($covered[$key])) {
                $missing[] = $key;
            }
        }
        $this->assertSame([], $missing, 'Every supported or partially supported feature needs a conformance fixture.');
        $this->assertSame([], array_diff(array_keys($covered), array_keys(CompatibilityReport::createDefault()->getFeatures())), 'Fixtures only cover known features.');
    }

    private function render(string $html, array $options): string
    {
        return FacadeScenario::render($html, $options);
    }
}
