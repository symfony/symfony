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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\OutputWrapper;

class OutputWrapperTest extends TestCase
{
    /**
     * @dataProvider textProvider
     */
    public function testBasicWrap(string $text, int $width, bool $allowCutUrls, string $expected)
    {
        $wrapper = new OutputWrapper($allowCutUrls);
        $result = $wrapper->wrap($text, $width);
        $this->assertEquals($expected, $result);
    }

    public function testWrapKeepsMalformedUtf8()
    {
        $wrapper = new OutputWrapper();

        $this->assertSame("Lorem\n\xB3\nipsum", $wrapper->wrap("Lorem \xB3 ipsum", 5));
        $this->assertSame("Lorem\n\xF0\x9F", $wrapper->wrap("Lorem \xF0\x9F", 5));
        $this->assertSame("<info>Lorem\n\xB3</info>\nhttps://example.com/\nipsum ", $wrapper->wrap("<info>Lorem \xB3</info> https://example.com/\nipsum \n", 5));
    }

    public function testWrapBreaksMalformedUtf8LikeValidText()
    {
        $wrapper = new OutputWrapper();

        $this->assertSame($wrapper->wrap('Lorem X ipsum', 5), str_replace("\xB3", 'X', $wrapper->wrap("Lorem \xB3 ipsum", 5)));
    }

    public function testWrapCountsWideCharactersAsTwoColumns()
    {
        $wrapper = new OutputWrapper();

        $this->assertSame("日本語\nテキス\nト", $wrapper->wrap('日本語テキスト', 6));
        $this->assertSame("日本\n語テ\nキス\nト", $wrapper->wrap('日本語テキスト', 5));
        $this->assertSame("日\n本", $wrapper->wrap('日本', 1));
        $this->assertSame("abc\n日本語\ndef", $wrapper->wrap('abc 日本語 def', 6));
        $this->assertSame("a日本\nb", $wrapper->wrap('a日本b', 5));
        $this->assertSame("<info>日本\n語</info>", $wrapper->wrap('<info>日本語</info>', 5));
        $this->assertSame("日本\nhttps://日本語.example.com/", $wrapper->wrap('日本 https://日本語.example.com/', 5));
    }

    public function testWrapKeepsNoncharactersOfTheText()
    {
        $wrapper = new OutputWrapper();

        $this->assertSame("aaaa\n\u{FDD0}bbb\nb", $wrapper->wrap("aaaa\u{FDD0}bbbb", 4));
        $this->assertSame("\u{FDD0}日本", $wrapper->wrap("\u{FDD0}日本", 5));
    }

    public function testWrapKeepsEscapedTagDelimitersWithTheirBackslash()
    {
        $wrapper = new OutputWrapper();

        $this->assertSame("aaaa\n\\<b", $wrapper->wrap('aaaa\<b', 5));
        $this->assertSame("aaaa\n\\>b", $wrapper->wrap('aaaa\>b', 5));
        $this->assertSame("aaa\\<\nb", $wrapper->wrap('aaa\<b', 5));
        $this->assertSame("aaaa\n\\</b\\>c", $wrapper->wrap('aaaa\</b\>c', 5));
    }

    public function testWrapDoesNotCountTagsInTheWidth()
    {
        $wrapper = new OutputWrapper();

        $this->assertSame('aaaa <info>bbbb</info> cccc', $wrapper->wrap('aaaa <info>bbbb</info> cccc', 14));
        $this->assertSame("aaaa <info>bbbb</info>\ncccc", $wrapper->wrap('aaaa <info>bbbb</info> cccc', 9));
        $this->assertSame("<href=https://symfony.com>aaaa</>\nbbbb", $wrapper->wrap('<href=https://symfony.com>aaaa</> bbbb', 4));
        $this->assertSame("aaaa\n</>\n<info>", $wrapper->wrap("aaaa\n</>\n<info>", 4));
        $this->assertSame("<info>\n\\<b>c", $wrapper->wrap('<info>\<b>c', 1));
    }

    public function testWrapCountsUrlsWithTheirWidthWithoutCuttingThem()
    {
        $wrapper = new OutputWrapper();

        $this->assertSame("aaaa\nhttp://a.b", $wrapper->wrap('aaaa http://a.b', 12));
        $this->assertSame("See\nhttps://symfony.com/doc/current/console.html\nfor more", $wrapper->wrap('See https://symfony.com/doc/current/console.html for more', 10));
        $this->assertSame("See\n<href=https://symfony.com>https://symfony.com/doc</>\nfor more", $wrapper->wrap('See <href=https://symfony.com>https://symfony.com/doc</> for more', 10));
        $this->assertSame("See\n(https://symfony.com/doc/current/console.html)\nfor more", $wrapper->wrap('See (https://symfony.com/doc/current/console.html) for more', 10));
        $this->assertSame("aaaaaaaaaa\naaahttps://symfony.com/doc", $wrapper->wrap('aaaaaaaaaaaaahttps://symfony.com/doc', 10));
        $this->assertSame('<info>aaa</info>https://symfony.com/doc', $wrapper->wrap('<info>aaa</info>https://symfony.com/doc', 5));
        $this->assertSame("a<info>b\n</info>https://symfony.com/doc", $wrapper->wrap('a<info>b</info>https://symfony.com/doc', 5));
        $this->assertSame("See\nhttps://symfony.com/doc\n\nfor more", $wrapper->wrap("See https://symfony.com/doc\n\nfor more", 10));
    }

    public function testWrapDoesNotDropBytesOfMalformedUtf8()
    {
        $text = "Cannot read the log file: \xB3 check the permissions";

        $wrapped = (new OutputWrapper())->wrap($text, 20);

        $this->assertNotSame($text, $wrapped);
        $this->assertSame(str_replace(' ', '', $text), str_replace(["\n", ' '], '', $wrapped));
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
                p
                https://github.com/symfony/symfony
                Lorem ipsum <comment>dolor</comment>
                sit amet,
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
    }
}
