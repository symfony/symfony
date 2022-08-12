<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String;

use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

class AsciiSluggerTest extends TestCase
{
    public function provideSlugTests(): iterable
    {
        yield ['', ''];
        yield ['foo', ' foo '];
        yield ['foo-bar', 'foo bar'];

        yield ['foo-bar', 'foo@bar', '-'];
        yield ['foo-at-bar', 'foo@bar', '-', 'en'];

        yield ['e-a', 'é$!à'];
        yield ['e_a', 'é$!à', '_'];

        yield ['a', 'ä'];
        yield ['a', 'ä', '-', 'fr'];
        yield ['ae', 'ä', '-', 'de'];
        yield ['ae', 'ä', '-', 'de_fr']; // Ensure we get the parent locale
        yield [\function_exists('transliterator_transliterate') ? 'g' : '', 'ғ', '-'];
        yield [\function_exists('transliterator_transliterate') ? 'gh' : '', 'ғ', '-', 'uz'];
        yield [\function_exists('transliterator_transliterate') ? 'gh' : '', 'ғ', '-', 'uz_fr']; // Ensure we get the parent locale
    }

    /** @dataProvider provideSlugTests */
    public function testSlug(string $expected, string $string, string $separator = '-', string $locale = null)
    {
        $slugger = new AsciiSlugger();

        $this->assertSame($expected, (string) $slugger->slug($string, $separator, $locale));
    }
}
