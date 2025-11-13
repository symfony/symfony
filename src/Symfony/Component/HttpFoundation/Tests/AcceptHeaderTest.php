<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\AcceptHeaderItem;

class AcceptHeaderTest extends TestCase
{
    public function testFirst()
    {
        $header = AcceptHeader::fromString('text/plain; q=0.5, text/html, text/x-dvi; q=0.8, text/x-c');
        $this->assertSame('text/html', $header->first()->getValue());
    }

    #[DataProvider('provideFromStringData')]
    public function testFromString($string, array $items)
    {
        $header = AcceptHeader::fromString($string);
        $parsed = array_values($header->all());
        // reset index since the fixtures don't have them set
        foreach ($parsed as $item) {
            $item->setIndex(0);
        }
        $this->assertEquals($items, $parsed);
    }

    public static function provideFromStringData()
    {
        return [
            ['', []],
            [';;;', []],
            ['0', [new AcceptHeaderItem('0')]],
            ['gzip', [new AcceptHeaderItem('gzip')]],
            ['gzip,deflate,sdch', [new AcceptHeaderItem('gzip'), new AcceptHeaderItem('deflate'), new AcceptHeaderItem('sdch')]],
            ["gzip, deflate\t,sdch", [new AcceptHeaderItem('gzip'), new AcceptHeaderItem('deflate'), new AcceptHeaderItem('sdch')]],
            ['"this;should,not=matter"', [new AcceptHeaderItem('this;should,not=matter')]],
        ];
    }

    #[DataProvider('provideToStringData')]
    public function testToString(array $items, $string)
    {
        $header = new AcceptHeader($items);
        $this->assertEquals($string, (string) $header);
    }

    public static function provideToStringData()
    {
        return [
            [[], ''],
            [[new AcceptHeaderItem('gzip')], 'gzip'],
            [[new AcceptHeaderItem('gzip'), new AcceptHeaderItem('deflate'), new AcceptHeaderItem('sdch')], 'gzip,deflate,sdch'],
            [[new AcceptHeaderItem('this;should,not=matter')], 'this;should,not=matter'],
        ];
    }

    #[DataProvider('provideFilterData')]
    public function testFilter($string, $filter, array $values)
    {
        $header = AcceptHeader::fromString($string)->filter($filter);
        $this->assertEquals($values, array_keys($header->all()));
    }

    public static function provideFilterData()
    {
        return [
            ['fr-FR,fr;q=0.8,en-US;q=0.6,en;q=0.4', '/fr.*/', ['fr-FR', 'fr']],
        ];
    }

    #[DataProvider('provideSortingData')]
    public function testSorting($string, array $values)
    {
        $header = AcceptHeader::fromString($string);
        $this->assertEquals($values, array_keys($header->all()));
    }

    public static function provideSortingData()
    {
        return [
            'quality has priority' => ['*;q=0.3,ISO-8859-1,utf-8;q=0.7', ['ISO-8859-1', 'utf-8', '*']],
            'order matters when q is equal' => ['*;q=0.3,ISO-8859-1;q=0.7,utf-8;q=0.7', ['ISO-8859-1', 'utf-8', '*']],
            'order matters when q is equal2' => ['*;q=0.3,utf-8;q=0.7,ISO-8859-1;q=0.7', ['utf-8', 'ISO-8859-1', '*']],
            'additional attributes like "format" should be handled according RFC 9110' => ['text/*;q=0.3, text/plain;q=0.7, text/plain;format=flowed, text/plain;format=fixed;q=0.4, */*;q=0.5', ['text/plain;format=flowed', 'text/plain', '*/*', 'text/plain;format=fixed', 'text/*']],
            'additional attributes like "format" should be handled according obsoleted RFC 7231 as well' => ['text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5', ['text/html;level=1', 'text/html', '*/*', 'text/html;level=2', 'text/*']],
        ];
    }

    #[DataProvider('provideDefaultValueData')]
    public function testDefaultValue($acceptHeader, $value, $expectedQuality)
    {
        $header = AcceptHeader::fromString($acceptHeader);
        $this->assertSame($expectedQuality, $header->get($value)?->getQuality());
    }

    public static function provideDefaultValueData()
    {
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, *;q=0.3', 'text/xml', 0.3];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*;q=0.3', 'text/xml', 0.3];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*;q=0.3', 'text/html', 1.0];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*;q=0.3', 'text/plain', 0.5];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*;q=0.3', '*', 0.3];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*', '*', 1.0];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*', 'text/xml', 1.0];
        yield ['text/plain;q=0.5, text/html, text/x-dvi;q=0.8, */*', 'text/*', 1.0];
        yield ['text/plain;q=0.5, text/html, text/*;q=0.8, */*', 'text/*', 0.8];
        yield ['text/plain;q=0.5, text/html, text/*;q=0.8, */*', 'text/html', 1.0];
        yield ['text/plain;q=0.5, text/html, text/*;q=0.8, */*', 'text/x-dvi', 0.8];
        yield ['*;q=0.3, ISO-8859-1;q=0.7, utf-8;q=0.7', '*', 0.3];
        yield ['*;q=0.3, ISO-8859-1;q=0.7, utf-8;q=0.7', 'utf-8', 0.7];
        yield ['*;q=0.3, ISO-8859-1;q=0.7, utf-8;q=0.7', 'SHIFT_JIS', 0.3];
        yield 'additional attributes like "format" should be handled according RFC 9110' => ['text/*;q=0.3, text/plain;q=0.7, text/plain;format=flowed, text/plain;format=fixed;q=0.4, */*;q=0.5', 'text/plain;format=flowed', 1.0];
        yield 'additional attributes like "format" should be handled according obsoleted RFC 7231 as well' => ['text/*;q=0.3, text/html;q=0.7, text/html;level=1, text/html;level=2;q=0.4, */*;q=0.5', 'text/html;level=1', 1.0];

        // Edge cases for case-insensitivity
        yield 'case-insensitive param names' => ['text/plain;format=flowed;q=0.8, text/plain;Format=fixed', 'text/plain;format=fixed', 1.0];
        yield 'case-insensitive charset' => ['text/plain;Charset=utf-8', 'text/plain;charset=utf-8', 1.0];

        // Quoted values and specials
        yield 'quoted value with space' => ['text/plain;param="value with space"', 'text/plain;param="value with space"', 1.0];
        yield 'quoted value with backslash' => ['text/plain;param="value\\with\\backslash"', 'text/plain;param="value\\with\\backslash"', 1.0];
        yield 'mismatched quoted' => ['text/plain;param="value with space"', 'text/plain;param=value with space', 1.0];

        // Flag params or empty
        yield 'flag param' => ['text/plain;flowed;q=0.9', 'text/plain;flowed', 0.9];
        yield 'empty param value' => ['text/plain;param=', 'text/plain;param=""', 1.0];
        yield 'missing required flag' => ['text/plain;flowed', 'text/plain', null];

        // Extra params in query
        yield 'extra param in query' => ['text/plain;format=flowed', 'text/plain;format=flowed;charset=utf-8', 1.0];
        yield 'missing required param in query' => ['text/plain;format=flowed', 'text/plain;charset=utf-8', null];
        yield 'wildcard with param' => ['text/*;format=flowed', 'text/plain;format=flowed', 1.0];
        yield 'wildcard missing param' => ['text/*;format=flowed', 'text/plain', null];

        // Wildcards and specificity
        yield 'specificity priority' => ['*/*;q=0.1, text/*;format=flowed;q=0.5, text/plain;q=0.8', 'text/plain;format=flowed', 0.8];
        yield 'wildcard with param match' => ['*/*;param=test', 'text/plain;param=test', 1.0];
        yield 'wildcard with param no match' => ['*/*;param=test', 'text/plain', null];

        // Non-media types
        yield 'charset wildcard' => ['utf-8;q=0.9, *;q=0.5', 'iso-8859-1', 0.5];
        yield 'language star' => ['*;q=0.5', 'en-US', 0.5];
        yield 'non-media */*' => ['*/*;q=0.5', 'utf-8', 0.5];

        // Ties and duplicates
        yield 'duplicate params tie on index' => ['text/plain;format=flowed;q=0.8, text/plain;format=flowed;q=0.8', 'text/plain;format=flowed', 0.8];
        yield 'param count tie' => ['text/plain;q=0.5, text/plain;format=flowed;q=0.5', 'text/plain;format=flowed;extra=foo', 0.5];

        // Invalid/malformed
        yield 'non-media invalid' => ['text', 'text', 1.0];
        yield 'invalid subtype' => ['text/', 'text/plain', null];
        yield 'empty header' => ['', 'text/plain', null];

        // Mixed case types
        yield 'mixed case type' => ['Text/Plain;Format=flowed', 'text/plain;format=flowed', 1.0];
        yield 'mixed case charset' => ['UTF-8;q=0.9', 'utf-8', 0.9];
    }
}
