<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\OutputWrapper;

class OutputWrapperTest extends TestCase
{
    #[DataProvider('textProvider')]
    public function testBasicWrap(string $text, int $width, bool $allowCutUrls, string $expected)
    {
        $wrapper = new OutputWrapper($allowCutUrls);
        $result = $wrapper->wrap($text, $width);
        $this->assertEquals($expected, $result);
    }

    public function testWrapReplacesMalformedUtf8()
    {
        $wrapper = new OutputWrapper();

        $this->assertSame("Lorem\n\u{FFFD}\nipsum", $wrapper->wrap("Lorem \xB3 ipsum", 5));
        $this->assertSame("Lorem\n\u{FFFD}", $wrapper->wrap("Lorem \xF0\x9F", 5));
        $this->assertSame("<info>Lore\nm \u{FFFD}</info>\nhttps://example.com/\nipsum ", $wrapper->wrap("<info>Lorem \xB3</info> https://example.com/\nipsum \n", 5));
    }

    public function testWrapBreaksMalformedUtf8LikeValidText()
    {
        $wrapper = new OutputWrapper();

        $this->assertSame($wrapper->wrap('Lorem X ipsum', 5), str_replace("\u{FFFD}", 'X', $wrapper->wrap("Lorem \xB3 ipsum", 5)));
    }

    public function testWrapKeepsTheWholeMessageOfMalformedUtf8()
    {
        $text = "Cannot read the log file: \xB3 check the permissions";

        $wrapped = (new OutputWrapper())->wrap($text, 20);

        $this->assertNotSame($text, $wrapped);
        $this->assertSame(str_replace([' ', "\xB3"], ['', "\u{FFFD}"], $text), str_replace(["\n", ' '], '', $wrapped));
    }

    public static function textProvider(): iterable
    {
        $baseTextWithUtf8AndUrl = 'Árvíztűrőtükörfúrógép https://github.com/symfony/symfony Lorem ipsum <comment>dolor</comment> sit amet, consectetur adipiscing elit. Praesent vestibulum nulla quis urna maximus porttitor. Donec ullamcorper risus at <error>libero ornare</error> efficitur.';

        yield 'Default URL cut' => [
            $baseTextWithUtf8AndUrl,
            20,
            false,
            <<<'EOS'
                Árvíztűrőtükörfúrógé
                p https://github.com/symfony/symfony Lorem ipsum
                <comment>dolor</comment> sit amet,
                consectetur
                adipiscing elit.
                Praesent vestibulum
                nulla quis urna
                maximus porttitor.
                Donec ullamcorper
                risus at <error>libero
                ornare</error> efficitur.
                EOS,
        ];

        yield 'Allow URL cut' => [
            $baseTextWithUtf8AndUrl,
            20,
            true,
            <<<'EOS'
                Árvíztűrőtükörfúrógé
                p
                https://github.com/s
                ymfony/symfony Lorem
                ipsum <comment>dolor</comment> sit
                amet, consectetur
                adipiscing elit.
                Praesent vestibulum
                nulla quis urna
                maximus porttitor.
                Donec ullamcorper
                risus at <error>libero
                ornare</error> efficitur.
                EOS,
        ];

        yield 'Width above the PCRE compiled-size limit' => [
            $baseTextWithUtf8AndUrl,
            150,
            false,
            <<<'EOS'
                Árvíztűrőtükörfúrógép https://github.com/symfony/symfony Lorem ipsum <comment>dolor</comment> sit amet, consectetur adipiscing elit. Praesent vestibulum nulla quis urna maximus porttitor. Donec
                ullamcorper risus at <error>libero ornare</error> efficitur.
                EOS,
        ];

        yield 'Width above the quantifier limit' => [
            $baseTextWithUtf8AndUrl,
            100000,
            false,
            $baseTextWithUtf8AndUrl,
        ];
    }
}
