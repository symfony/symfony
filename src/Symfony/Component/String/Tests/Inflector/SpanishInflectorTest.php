<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String\Tests\Inflector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Inflector\SpanishInflector;

class SpanishInflectorTest extends TestCase
{
    public static function singularizeProvider(): array
    {
        return [
            // vowels (RAE 3.2a, 3.2c)
            ['peras', 'pera'],
            ['especies', 'especie'],
            ['álcalis', 'álcali'],
            ['códigos', 'código'],
            ['espíritus', 'espíritu'],

            // accented (RAE 3.2a, 3.2c)
            ['papás', 'papá'],
            ['cafés', 'café'],
            ['isrealís', 'isrealí'],
            ['burós', 'buró'],
            ['tisús', 'tisú'],

            // ending in -ión
            ['aviones', 'avión'],
            ['camiones', 'camión'],

            // ending in some letters (RAE 3.2k)
            ['amores', 'amor'],
            ['antifaces', 'antifaz'],
            ['atriles', 'atril'],
            ['fácsimiles', 'fácsimil'],
            ['vides', 'vid'],
            ['reyes', 'rey'],
            ['relojes', 'reloj'],
            ['faxes', 'fax'],
            ['sándwiches', 'sándwich'],
            ['cánones', 'cánon'],

            // (RAE 3.2n)
            ['adioses', 'adiós'],
            ['aguarrases', 'aguarrás'],
            ['arneses', 'arnés'],
            ['autobuses', 'autobús'],
            ['kermeses', 'kermés'],
            ['palmareses', 'palmarés'],
            ['toses', 'tos'],

            // Special
            ['síes', 'sí'],
            ['noes', 'no'],
        ];
    }

    public static function pluralizeProvider(): array
    {
        return [
            // vowels (RAE 3.2a, 3.2c)
            ['pera', 'peras'],
            ['especie', 'especies'],
            ['álcali', 'álcalis'],
            ['código', 'códigos'],
            ['espíritu', 'espíritus'],

            // accented (RAE 3.2a, 3.2c)
            ['papá', 'papás'],
            ['café', 'cafés'],
            ['isrealí', 'isrealís'],
            ['buró', 'burós'],
            ['tisú', 'tisús'],

            // ending in -ión
            ['avión', 'aviones'],
            ['camión', 'camiones'],

            // ending in some letters (RAE 3.2k)
            ['amor', 'amores'],
            ['antifaz', 'antifaces'],
            ['atril', 'atriles'],
            ['fácsimil', 'fácsimiles'],
            ['vid', 'vides'],
            ['rey', 'reyes'],
            ['reloj', 'relojes'],
            ['fax', 'faxes'],
            ['sándwich', 'sándwiches'],
            ['cánon', 'cánones'],

            // (RAE 3.2n)
            ['adiós', 'adioses'],
            ['aguarrás', 'aguarrases'],
            ['arnés', 'arneses'],
            ['autobús', 'autobuses'],
            ['kermés', 'kermeses'],
            ['palmarés', 'palmareses'],
            ['tos', 'toses'],

            // Specials
            ['sí', 'síes'],
            ['no', 'noes'],
        ];
    }

    public static function uninflectedProvider(): array
    {
        return [
            ['lunes'],
            ['rodapiés'],
            ['reposapiés'],
            ['miércoles'],
            ['pies'],
        ];
    }

    /**
     * @dataProvider singularizeProvider
     */
    public function testSingularize(string $plural, $singular)
    {
        $this->assertSame(
            \is_array($singular) ? $singular : [$singular],
            (new SpanishInflector())->singularize($plural)
        );
    }

    /**
     * @dataProvider pluralizeProvider
     */
    public function testPluralize(string $singular, $plural)
    {
        $this->assertSame(
            \is_array($plural) ? $plural : [$plural],
            (new SpanishInflector())->pluralize($singular)
        );
    }

    /**
     * @dataProvider uninflectedProvider
     */
    public function testUninflected(string $word)
    {
        $this->assertSame(
            [$word],
            (new SpanishInflector())->pluralize($word)
        );
    }
}
