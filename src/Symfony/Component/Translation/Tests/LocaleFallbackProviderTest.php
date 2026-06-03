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
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\LocaleFallbackProvider;

class LocaleFallbackProviderTest extends TestCase
{
    public function testConstructorValidatesLocales()
    {
        $this->expectException(InvalidArgumentException::class);

        new LocaleFallbackProvider(['en', 'invalid locale!']);
    }

    public function testComputeFallbackLocalesValidatesLocale()
    {
        $this->expectException(InvalidArgumentException::class);

        (new LocaleFallbackProvider())->computeFallbackLocales('invalid locale!');
    }

    public function testComputeFallbackLocalesShortensSubTags()
    {
        $provider = new LocaleFallbackProvider();

        $this->assertSame(['en'], $provider->computeFallbackLocales('en_US'));
    }

    #[DataProvider('provideIcuParentLocales')]
    public function testComputeFallbackLocalesUsesIcuParents(string $locale, array $expected)
    {
        $provider = new LocaleFallbackProvider();

        $this->assertSame($expected, $provider->computeFallbackLocales($locale));
    }

    public static function provideIcuParentLocales(): array
    {
        return [
            'ICU root parent terminates chain' => ['az_Cyrl', []],
            'ICU explicit parent chain' => ['en_150', ['en_001', 'en']],
            'locale sub-tag shortening' => ['sl_Latn_IT', ['sl_Latn', 'sl']],
        ];
    }

    public function testComputeFallbackLocalesAppendsUltimateFallbacks()
    {
        $provider = new LocaleFallbackProvider(['de', 'fr']);

        $result = $provider->computeFallbackLocales('en_US');

        $this->assertSame(['en', 'de', 'fr'], $result);
    }

    public function testComputeFallbackLocalesExcludesOriginFromUltimateFallbacks()
    {
        $provider = new LocaleFallbackProvider(['en_US', 'fr']);

        $result = $provider->computeFallbackLocales('en_US');

        $this->assertSame(['en', 'fr'], $result);
    }

    public function testComputeFallbackLocalesReturnsUniqueLocales()
    {
        $provider = new LocaleFallbackProvider(['en', 'fr']);

        // en_US -> en (sub-tag shortening) -> en (ultimate fallback, duplicate)
        $result = $provider->computeFallbackLocales('en_US');

        $this->assertSame(['en', 'fr'], $result);
    }

    public function testComputeFallbackLocalesForRootIcuParentReturnsEmpty()
    {
        // az_Cyrl has ICU explicit parent 'root', meaning no fallback chain
        $provider = new LocaleFallbackProvider();

        $this->assertSame([], $provider->computeFallbackLocales('az_Cyrl'));
    }

    #[DataProvider('provideValidLocales')]
    public function testValidateLocalePassesForValidLocales(string $locale)
    {
        LocaleFallbackProvider::validateLocale($locale);

        $this->addToAssertionCount(1);
    }

    public static function provideValidLocales(): array
    {
        return [
            ['en'],
            ['en_US'],
            ['en-US'],
            ['fr_FR.UTF8'],
            ['sr@latin'],
            [''],
        ];
    }

    #[DataProvider('provideInvalidLocales')]
    public function testValidateLocaleThrowsForInvalidLocales(string $locale)
    {
        $this->expectException(InvalidArgumentException::class);

        LocaleFallbackProvider::validateLocale($locale);
    }

    public static function provideInvalidLocales(): array
    {
        return [
            ['fr FR'],
            ['français'],
            ['fr+en'],
            ['utf#8'],
            ['fr&en'],
            ['fr~FR'],
            [' fr'],
            ['fr '],
            ['fr*'],
            ['fr/FR'],
            ['fr\\FR'],
        ];
    }
}
