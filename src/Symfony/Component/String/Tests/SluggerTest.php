<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

class SluggerTest extends TestCase
{
    /**
     * @requires extension intl
     * @dataProvider provideSlug
     */
    public function testSlug(string $string, string $locale, string $expectedSlug)
    {
        $slugger = new AsciiSlugger($locale);

        $this->assertSame($expectedSlug, (string) $slugger->slug($string));
    }

    public static function provideSlug(): array
    {
        return [
            ['Стойността трябва да бъде лъжа', 'bg', 'Stoinostta-tryabva-da-bude-luzha'],
            ['You & I', 'en', 'You-and-I'],
            ['symfony@symfony.com', 'en', 'symfony-at-symfony-com'],
            ['Dieser Wert sollte größer oder gleich', 'de', 'Dieser-Wert-sollte-groesser-oder-gleich'],
            ['Dieser Wert sollte größer oder gleich', 'de_AT', 'Dieser-Wert-sollte-groesser-oder-gleich'],
            ['Αυτή η τιμή πρέπει να είναι ψευδής', 'el', 'Avti-i-timi-prepi-na-inai-psevdhis'],
            ['该变量的值应为', 'zh', 'gai-bian-liang-de-zhi-ying-wei'],
            ['該變數的值應為', 'zh_TW', 'gai-bian-shu-de-zhi-ying-wei'],
            ['Wôrķšƥáçè ~~sèťtïñğš~~', 'C', 'Workspace-settings'],
        ];
    }

    public function testSeparatorWithoutLocale()
    {
        $slugger = new AsciiSlugger();

        $this->assertSame('hello-world', (string) $slugger->slug('hello world'));
        $this->assertSame('hello_world', (string) $slugger->slug('hello world', '_'));
    }

    public function testSlugCharReplacementLocaleConstruct()
    {
        $slugger = new AsciiSlugger('fr', ['fr' => ['&' => 'et', '@' => 'chez']]);
        $slug = (string) $slugger->slug('toi & moi avec cette adresse slug@test.fr', '_');

        $this->assertSame('toi_et_moi_avec_cette_adresse_slug_chez_test_fr', $slug);
    }

    public function testSlugCharReplacementLocaleMethod()
    {
        $slugger = new AsciiSlugger(null, ['es' => ['&' => 'y', '@' => 'en senal']]);
        $slug = (string) $slugger->slug('yo & tu a esta dirección slug@test.es', '_', 'es');
        $this->assertSame('yo_y_tu_a_esta_direccion_slug_en_senal_test_es', $slug);
    }
}
