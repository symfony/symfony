<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\PseudoLocalizationTranslator;

final class PseudoLocalizationTranslatorTest extends TestCase
{
    #[DataProvider('provideTrans')]
    public function testTrans(string $expected, string $input, array $options = [])
    {
        mt_srand(987);
        $this->assertSame($expected, (new PseudoLocalizationTranslator(new IdentityTranslator(), $options))->trans($input));
    }

    public static function provideTrans(): array
    {
        return [
            ['[ƒöö⭐ ≤þ≥ƁÅŔ≤⁄þ≥]', 'foo⭐ <p>BAR</p>'], // Test defaults
            ['before <div data-label="fcy"><a href="#" title="bar" data-content="ccc">foo</a></div> after', 'before <div data-label="fcy"><a href="#" title="bar" data-content="ccc">foo</a></div> after', self::getIsolatedOptions(['parse_html' => true])],
            ['ƀéƒöŕé <div data-label="fcyéé"><a href="#" title="bar" data-content="ccc">ƒöö éé</a></div> åƒţéŕ', 'before <div data-label="fcyéé"><a href="#" title="bar" data-content="ccc">foo éé</a></div> after', self::getIsolatedOptions(['parse_html' => true, 'accents' => true])],
            ['ƀéƒöŕé <div data-label="ƒçý"><a href="#" title="ƀåŕ" data-content="ccc">ƒöö</a></div> åƒţéŕ', 'before <div data-label="fcy"><a href="#" title="bar" data-content="ccc">foo</a></div> after', self::getIsolatedOptions(['parse_html' => true, 'localizable_html_attributes' => ['data-label', 'title'], 'accents' => true])],
            [' ¡″♯€‰⅋´{}⁎⁺،‐·⁄⓪①②③④⑤⑥⑦⑧⑨∶⁏≤≂≥¿՞ÅƁÇÐÉƑĜĤÎĴĶĻṀÑÖÞǪŔŠŢÛṼŴẊÝŽ⁅∖⁆˄‿‵åƀçðéƒĝĥîĵķļɱñöþǫŕšţûṽŵẋýž(¦)˞', ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~', self::getIsolatedOptions(['accents' => true])],
            ['foo <p>bar</p> ~~~~~~~~~~ ~~', 'foo <p>bar</p>', self::getIsolatedOptions(['expansion_factor' => 2.0])],
            ['foo <p>bar</p> ~~~ ~~', 'foo <p>bar</p>', self::getIsolatedOptions(['parse_html' => true, 'expansion_factor' => 2.0])], // Only the visible text length is expanded
            ['foobar ~~', 'foobar', self::getIsolatedOptions(['expansion_factor' => 1.35])], // 6*1.35 = 8.1 but we round up to 9
            ['[foobar]', 'foobar', self::getIsolatedOptions(['brackets' => true])],
            ['[foobar ~~~]', 'foobar', self::getIsolatedOptions(['expansion_factor' => 2.0, 'brackets' => true])], // The added brackets are taken into account in the expansion
            ['<p data-foo="&quot;ççç&lt;å">ƀåŕ</p>', '<p data-foo="&quot;ccc&lt;a">bar</p>', self::getIsolatedOptions(['parse_html' => true, 'localizable_html_attributes' => ['data-foo'], 'accents' => true])],
            ['<p data-foo="ççéç&quot;&quot;">ƀåéŕ</p>', '<p data-foo="ccéc&quot;&quot;">baér</p>', self::getIsolatedOptions(['parse_html' => true, 'localizable_html_attributes' => ['data-foo'], 'accents' => true])],
            ['<p data-foo="ccc&quot;&quot;">ƀåŕ</p>', '<p data-foo="ccc&quot;&quot;">bar</p>', self::getIsolatedOptions(['parse_html' => true, 'accents' => true])],
            ['<p>″≤″</p>', '<p>&quot;&lt;&quot;</p>', self::getIsolatedOptions(['parse_html' => true, 'accents' => true])],
            ['Symfony is an Open Source, community-driven project with thousands of contributors. ~~~~~~~ ~~ ~~~~ ~~~~~~~ ~~~~~~~ ~~ ~~~~ ~~~~~~~~~~~~~ ~~~~~~~~~~~~~ ~~~~~~~ ~~ ~~~', 'Symfony is an Open Source, community-driven project with thousands of contributors.', self::getIsolatedOptions(['expansion_factor' => 2.0])],
            ['<p>👇👇👇👇👇👇👇</p>', '<p>👇👇👇👇👇👇👇</p>', self::getIsolatedOptions(['parse_html' => true])],
        ];
    }

    #[DataProvider('provideInvalidExpansionFactor')]
    public function testInvalidExpansionFactor(float $expansionFactor)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The expansion factor must be greater than or equal to 1.');

        new PseudoLocalizationTranslator(new IdentityTranslator(), [
            'expansion_factor' => $expansionFactor,
        ]);
    }

    public static function provideInvalidExpansionFactor(): array
    {
        return [
            [0],
            [0.99],
            [-1],
        ];
    }

    private static function getIsolatedOptions(array $options): array
    {
        return array_replace([
            'parse_html' => false,
            'localizable_html_attributes' => [],
            'accents' => false,
            'expansion_factor' => 1.0,
            'brackets' => false,
        ], $options);
    }

    public function testTransDoesNotResolveExternalEntities()
    {
        $networkLoads = [];
        libxml_set_external_entity_loader(static function (?string $public, string $system, array $context) use (&$networkLoads) {
            if (preg_match('#^(?:https?|ftp)://#i', $system)) {
                $networkLoads[] = $system;
            }

            return null;
        });

        try {
            $translator = new PseudoLocalizationTranslator(new IdentityTranslator(), ['parse_html' => true]);
            $output = $translator->trans('<!DOCTYPE html SYSTEM "http://127.0.0.1:1/payload.dtd"><p>hi</p>');
        } finally {
            libxml_set_external_entity_loader(null);
        }

        $this->assertSame([], $networkLoads, 'PseudoLocalizationTranslator must not resolve external entities over the network.');
        $this->assertIsString($output);
    }
}

// @php-cs-fixer-ignore random_api_migration As logic is coupled with mt_rand() in src
