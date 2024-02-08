<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Tests\TextSanitizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\TextSanitizer\StringSanitizer;

class StringSanitizerTest extends TestCase
{
    public static function provideHtmlLower()
    {
        $cases = [
            'exampleAttr' => 'exampleattr',
            'aTTrΔ' => 'attrΔ',
            'data-attr' => 'data-attr',
            'test with space' => 'test with space',
        ];

        foreach ($cases as $input => $expected) {
            yield $input => [$input, $expected];
        }
    }

    /**
     * @dataProvider provideHtmlLower
     */
    public function testHtmlLower(string $input, string $expected)
    {
        $this->assertSame($expected, StringSanitizer::htmlLower($input));
    }

    public static function provideEncodeHtmlEntites()
    {
        $cases = [
            '' => '',
            '"' => '&#34;',
            '\'' => '&#039;',
            '&' => '&amp;',
            '<' => '&lt;',
            '>' => '&gt;',
            '&lt;' => '&amp;lt;',
            '&gt;' => '&amp;gt;',
            '+' => '&#43;',
            '=' => '&#61;',
            '@' => '&#64;',
            '`' => '&#96;',
            '＜' => '&#xFF1C;',
            '＞' => '&#xFF1E;',
            '＋' => '&#xFF0B;',
            '＝' => '&#xFF1D;',
            '＠' => '&#xFF20;',
            '｀' => '&#xFF40;',
        ];

        foreach ($cases as $input => $expected) {
            yield $input => [$input, $expected];
        }
    }

    /**
     * @dataProvider provideEncodeHtmlEntites
     */
    public function testEncodeHtmlEntites(string $input, string $expected)
    {
        $this->assertSame($expected, StringSanitizer::encodeHtmlEntities($input));
    }
}
