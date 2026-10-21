<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class OutputFormatterTest extends TestCase
{
    public function testEmptyTag()
    {
        $formatter = new OutputFormatter(true);
        $this->assertEquals('foo<>bar', $formatter->format('foo<>bar'));
    }

    public function testLGCharEscaping()
    {
        $formatter = new OutputFormatter(true);

        $this->assertEquals('foo<bar', $formatter->format('foo\\<bar'));
        $this->assertEquals('foo << bar', $formatter->format('foo << bar'));
        $this->assertEquals('foo << bar \\', $formatter->format('foo << bar \\'));
        $this->assertEquals("foo << \033[32mbar \\ baz\033[39m \\", $formatter->format('foo << <info>bar \\ baz</info> \\'));
        $this->assertEquals('<info>some info</info>', $formatter->format('\\<info>some info\\</info>'));
        $this->assertEquals('\\<info\\>some info\\</info\\>', OutputFormatter::escape('<info>some info</info>'));
        // every < and > gets escaped, backslashes are left as they are
        $this->assertEquals('foo \\< bar \\\\< baz \\\\\\< foo \\> bar \\\\> baz \\\\\\> \\x', OutputFormatter::escape('foo < bar \\< baz \\\\< foo > bar \\> baz \\\\> \\x'));

        $this->assertEquals(
            "\033[33mSymfony\\Component\\Console does work very well!\033[39m",
            $formatter->format('<comment>Symfony\Component\Console does work very well!</comment>')
        );
    }

    public function testFormatRestoresEscapedText()
    {
        $formatter = new OutputFormatter(true);

        $this->assertEquals('foo \\<bar', $formatter->format(OutputFormatter::escape('foo \\<bar')));
        $this->assertEquals('foo \\>bar', $formatter->format(OutputFormatter::escape('foo \\>bar')));
        $this->assertEquals('foo \\\\<bar>', $formatter->format(OutputFormatter::escape('foo \\\\<bar>')));
        $this->assertEquals('foo \\<info>bar\\</info>', $formatter->format(OutputFormatter::escape('foo \\<info>bar\\</info>')));
        $this->assertEquals('foo \\', $formatter->format(OutputFormatter::escape('foo \\')));
    }

    public function testBundledStyles()
    {
        $formatter = new OutputFormatter(true);

        $this->assertTrue($formatter->hasStyle('error'));
        $this->assertTrue($formatter->hasStyle('info'));
        $this->assertTrue($formatter->hasStyle('comment'));
        $this->assertTrue($formatter->hasStyle('question'));

        $this->assertEquals(
            "\033[37;41msome error\033[39;49m",
            $formatter->format('<error>some error</error>')
        );
        $this->assertEquals(
            "\033[32msome info\033[39m",
            $formatter->format('<info>some info</info>')
        );
        $this->assertEquals(
            "\033[33msome comment\033[39m",
            $formatter->format('<comment>some comment</comment>')
        );
        $this->assertEquals(
            "\033[30;46msome question\033[39;49m",
            $formatter->format('<question>some question</question>')
        );
    }

    public function testNestedStyles()
    {
        $formatter = new OutputFormatter(true);

        $this->assertEquals(
            "\033[37;41msome \033[39;49m\033[32msome info\033[39m\033[37;41m error\033[39;49m",
            $formatter->format('<error>some <info>some info</info> error</error>')
        );
    }

    public function testAdjacentStyles()
    {
        $formatter = new OutputFormatter(true);

        $this->assertEquals(
            "\033[37;41msome error\033[39;49m\033[32msome info\033[39m",
            $formatter->format('<error>some error</error><info>some info</info>')
        );
    }

    public function testStyleMatchingNotGreedy()
    {
        $formatter = new OutputFormatter(true);

        $this->assertEquals(
            "(\033[32m>=2.0,<2.3\033[39m)",
            $formatter->format('(<info>>=2.0,<2.3</info>)')
        );
    }

    public function testStyleEscaping()
    {
        $formatter = new OutputFormatter(true);

        $this->assertEquals(
            "(\033[32mz>=2.0,<\\<<a2.3\\\033[39m)",
            $formatter->format('(<info>'.$formatter->escape('z>=2.0,<\\<<a2.3\\').'</info>)')
        );

        $this->assertEquals(
            "\033[32m<error>some error</error>\033[39m",
            $formatter->format('<info>'.$formatter->escape('<error>some error</error>').'</info>')
        );
    }

    public function testDeepNestedStyles()
    {
        $formatter = new OutputFormatter(true);

        $this->assertEquals(
            "\033[37;41merror\033[39;49m\033[32minfo\033[39m\033[33mcomment\033[39m\033[37;41merror\033[39;49m",
            $formatter->format('<error>error<info>info<comment>comment</info>error</error>')
        );
    }

    public function testNewStyle()
    {
        $formatter = new OutputFormatter(true);

        $style = new OutputFormatterStyle('blue', 'white');
        $formatter->setStyle('test', $style);

        $this->assertEquals($style, $formatter->getStyle('test'));
        $this->assertNotEquals($style, $formatter->getStyle('info'));

        $style = new OutputFormatterStyle('blue', 'white');
        $formatter->setStyle('b', $style);

        $this->assertEquals("\033[34;47msome \033[39;49m\033[34;47mcustom\033[39;49m\033[34;47m msg\033[39;49m", $formatter->format('<test>some <b>custom</b> msg</test>'));
    }

    public function testRedefineStyle()
    {
        $formatter = new OutputFormatter(true);

        $style = new OutputFormatterStyle('blue', 'white');
        $formatter->setStyle('info', $style);

        $this->assertEquals("\033[34;47msome custom msg\033[39;49m", $formatter->format('<info>some custom msg</info>'));
    }

    public function testInlineStyle()
    {
        $formatter = new OutputFormatter(true);

        $this->assertEquals("\033[34;41msome text\033[39;49m", $formatter->format('<fg=blue;bg=red>some text</>'));
        $this->assertEquals("\033[34;41msome text\033[39;49m", $formatter->format('<fg=blue;bg=red>some text</fg=blue;bg=red>'));
    }

    /**
     * @dataProvider provideInlineStyleOptionsCases
     */
    public function testInlineStyleOptions(string $tag, ?string $expected = null, ?string $input = null, bool $truecolor = false)
    {
        if ($truecolor && 'truecolor' !== getenv('COLORTERM')) {
            $this->markTestSkipped('The terminal does not support true colors.');
        }

        $styleString = substr($tag, 1, -1);
        $formatter = new OutputFormatter(true);
        $method = new \ReflectionMethod($formatter, 'createStyleFromString');

        /** @var OutputFormatterStyle|null $result */
        $result = $method->invoke($formatter, $styleString);

        if (null === $expected) {
            $this->assertNull($result);
            $expected = $tag.$input.'</'.$styleString.'>';
            $this->assertSame($expected, $formatter->format($expected));
        } else {
            $this->assertInstanceOf(OutputFormatterStyle::class, $result);
            $this->assertSame($expected, $formatter->format($tag.$input.'</>'));
            $this->assertSame($expected, $formatter->format($tag.$input.'</'.$styleString.'>'));
        }
    }

    public static function provideInlineStyleOptionsCases()
    {
        return [
            ['<unknown=_unknown_>'],
            ['<unknown=_unknown_;a=1;b>'],
            ['<fg=green;>', "\033[32m[test]\033[39m", '[test]'],
            ['<fg=green;bg=blue;>', "\033[32;44ma\033[39;49m", 'a'],
            ['<fg=green;options=bold>', "\033[32;1mb\033[39;22m", 'b'],
            ['<fg=green;options=reverse;>', "\033[32;7m<a>\033[39;27m", '<a>'],
            ['<fg=green;options=bold,underscore>', "\033[32;1;4mz\033[39;22;24m", 'z'],
            ['<fg=green;options=bold,underscore,reverse;>', "\033[32;1;4;7md\033[39;22;24;27m", 'd'],
            ['<fg=#00ff00;bg=#00f>', "\033[38;2;0;255;0;48;2;0;0;255m[test]\033[39;49m", '[test]', true],
        ];
    }

    public static function provideInlineStyleTagsWithUnknownOptions()
    {
        return [
            ['<options=abc;>', 'abc'],
            ['<options=abc,def;>', 'abc'],
            ['<fg=green;options=xyz;>', 'xyz'],
            ['<fg=green;options=efg,abc>', 'efg'],
        ];
    }

    public function testNonStyleTag()
    {
        $formatter = new OutputFormatter(true);

        $this->assertEquals("\033[32msome \033[39m\033[32m<tag>\033[39m\033[32m \033[39m\033[32m<setting=value>\033[39m\033[32m styled \033[39m\033[32m<p>\033[39m\033[32msingle-char tag\033[39m\033[32m</p>\033[39m", $formatter->format('<info>some <tag> <setting=value> styled <p>single-char tag</p></info>'));
    }

    public function testFormatLongString()
    {
        $formatter = new OutputFormatter(true);
        $long = str_repeat('\\', 14000);
        $this->assertEquals("\033[37;41msome error\033[39;49m".$long, $formatter->format('<error>some error</error>'.$long));
    }

    public function testFormatToStringObject()
    {
        $formatter = new OutputFormatter(false);
        $this->assertEquals(
            'some info', $formatter->format(new TableCell())
        );
    }

    public function testFormatterHasStyles()
    {
        $formatter = new OutputFormatter(false);

        $this->assertTrue($formatter->hasStyle('error'));
        $this->assertTrue($formatter->hasStyle('info'));
        $this->assertTrue($formatter->hasStyle('comment'));
        $this->assertTrue($formatter->hasStyle('question'));
    }

    /**
     * @dataProvider provideDecoratedAndNonDecoratedOutput
     */
    public function testNotDecoratedFormatterOnJediTermEmulator(string $input, string $expectedNonDecoratedOutput, string $expectedDecoratedOutput, bool $shouldBeJediTerm = false)
    {
        $terminalEmulator = $shouldBeJediTerm ? 'JetBrains-JediTerm' : 'Unknown';

        $prevTerminalEmulator = getenv('TERMINAL_EMULATOR');
        putenv('TERMINAL_EMULATOR='.$terminalEmulator);

        try {
            $this->assertEquals($expectedDecoratedOutput, (new OutputFormatter(true))->format($input));
            $this->assertEquals($expectedNonDecoratedOutput, (new OutputFormatter(false))->format($input));
        } finally {
            putenv('TERMINAL_EMULATOR'.($prevTerminalEmulator ? "=$prevTerminalEmulator" : ''));
        }
    }

    /**
     * @dataProvider provideDecoratedAndNonDecoratedOutput
     */
    public function testNotDecoratedFormatterOnIDEALikeEnvironment(string $input, string $expectedNonDecoratedOutput, string $expectedDecoratedOutput, bool $expectsIDEALikeTerminal = false)
    {
        // Backup previous env variable
        $previousValue = $_SERVER['IDEA_INITIAL_DIRECTORY'] ?? null;
        $hasPreviousValue = \array_key_exists('IDEA_INITIAL_DIRECTORY', $_SERVER);

        if ($expectsIDEALikeTerminal) {
            $_SERVER['IDEA_INITIAL_DIRECTORY'] = __DIR__;
        } elseif ($hasPreviousValue) {
            // Forcibly remove the variable because the test runner may contain it
            unset($_SERVER['IDEA_INITIAL_DIRECTORY']);
        }

        try {
            $this->assertEquals($expectedDecoratedOutput, (new OutputFormatter(true))->format($input));
            $this->assertEquals($expectedNonDecoratedOutput, (new OutputFormatter(false))->format($input));
        } finally {
            // Rollback previous env state
            if ($hasPreviousValue) {
                $_SERVER['IDEA_INITIAL_DIRECTORY'] = $previousValue;
            } else {
                unset($_SERVER['IDEA_INITIAL_DIRECTORY']);
            }
        }
    }

    public static function provideDecoratedAndNonDecoratedOutput()
    {
        return [
            ['<error>some error</error>', 'some error', "\033[37;41msome error\033[39;49m"],
            ['<info>some info</info>', 'some info', "\033[32msome info\033[39m"],
            ['<comment>some comment</comment>', 'some comment', "\033[33msome comment\033[39m"],
            ['<question>some question</question>', 'some question', "\033[30;46msome question\033[39;49m"],
            ['<fg=red>some text with inline style</>', 'some text with inline style', "\033[31msome text with inline style\033[39m"],
            ['<href=idea://open/?file=/path/SomeFile.php&line=12>some URL</>', 'some URL', "\033]8;;idea://open/?file=/path/SomeFile.php&line=12\033\\some URL\033]8;;\033\\"],
            ['<href=https://example.com/\<woohoo\>>some URL with \<woohoo\></>', 'some URL with <woohoo>', "\033]8;;https://example.com/<woohoo>\033\\some URL with <woohoo>\033]8;;\033\\"],
            ['<href=mailto:foo@example.com><foo@example.com></>', '<foo@example.com>', "\033]8;;mailto:foo@example.com\033\\<foo@example.com>\033]8;;\033\\"],
            ['<href=https://example.com>some URL</>> <foo>', 'some URL> <foo>', "\033]8;;https://example.com\033\\some URL\033]8;;\033\\> <foo>"],
            ['<href=idea://open/?file=/path/SomeFile.php&line=12>some URL</>', 'some URL', 'some URL', true],
        ];
    }

    public function testContentWithLineBreaks()
    {
        $formatter = new OutputFormatter(true);

        $this->assertEquals(
            <<<EOF
                \033[32m
                some text\033[39m
                EOF,
            $formatter->format(<<<'EOF'
                <info>
                some text</info>
                EOF
            )
        );

        $this->assertEquals(
            <<<EOF
                \033[32msome text
                \033[39m
                EOF,
            $formatter->format(<<<'EOF'
                <info>some text
                </info>
                EOF
            )
        );

        $this->assertEquals(
            <<<EOF
                \033[32m
                some text
                \033[39m
                EOF,
            $formatter->format(<<<'EOF'
                <info>
                some text
                </info>
                EOF
            )
        );

        $this->assertEquals(
            <<<EOF
                \033[32m
                some text
                more text
                \033[39m
                EOF,
            $formatter->format(<<<'EOF'
                <info>
                some text
                more text
                </info>
                EOF
            )
        );
    }

    public function testFormatAndWrapPreservesAnsiSequences()
    {
        $formatter = new OutputFormatter(true);

        $this->assertSame("\e]8;;https://example.com\e\\foo\nbar\e]8;;\e\\", $formatter->formatAndWrap("\e]8;;https://example.com\e\\foo bar\e]8;;\e\\", 3));
        $this->assertSame("\e[31mfoo\nbar\nbaz\e[0m", $formatter->formatAndWrap("\e[31mfoo bar baz\e[0m", 5));
        $this->assertSame("fo\e[31mo\nbar\e[0m", $formatter->formatAndWrap("fo\e[31mobar\e[0m", 3));
        $this->assertSame("\e[0;38;5;208m  foo\e[m", $formatter->formatAndWrap("\e[0;38;5;208m  foo\e[m", 8));
        $this->assertSame("\e[31mfoo\e[0m\n\e[32mbar\e[0m", $formatter->formatAndWrap("\e[31mfoo\e[0m\n\e[32mbar\e[0m", 3));
        $this->assertSame("\e[31m👩‍🌾\nfo\no\e[0m", $formatter->formatAndWrap("\e[31m👩‍🌾foo\e[0m", 2));
        $this->assertSame("\e[32mabc\e[39m\e[0m", $formatter->formatAndWrap("<info>abc</info>\e[0m", 3));
        $this->assertSame("\e[32mab\e[39m\e[31mcd\e[0m", $formatter->formatAndWrap("<info>ab</info>\e[31mcd\e[0m", 4));
        $this->assertSame("\e[32mab\e[39m\e[31mcd\nef\e[0m", $formatter->formatAndWrap("<info>ab</info>\e[31mcdef\e[0m", 4));
        $this->assertSame("\e[32mab\e[39m\e[31m\e[0m\n", $formatter->formatAndWrap("<info>ab</info>\e[31m\n\e[0m", 5));
        $this->assertSame("a\n\nb", preg_replace('/\e\[[0-9;]*m/', '', $formatter->formatAndWrap("a\n<info>\e[31m\n\e[0m</info>b", 5)));
        $this->assertSame("foo\e[0m\n\n\e[37;41mbar\e[39;49m", $formatter->formatAndWrap("foo\n\n\e[0m<error>bar</error>", 11));
        $this->assertSame("abcdef\nfoo", preg_replace('/\e\[[0-9;]*m/', '', $formatter->formatAndWrap("<info>abcdef</info>\e[31m\nfoo", 6)));
    }

    public function testFormatAndWrapLongRunsOfAnsiSequencesAndWhitespace()
    {
        $formatter = new OutputFormatter(true);

        $message = "\e[31mfoo\e[0m".str_repeat("\n", 20000).'bar';
        $this->assertSame($message, $formatter->formatAndWrap($message, 5));
        $this->assertSame("\e[32mabcde\e[39m\n\e[31mbar", $formatter->formatAndWrap("<info>abcde</info>\e[31m".str_repeat(' ', 20000).'bar', 5));
        $this->assertSame("\e[31mfoo".str_repeat("\e[0m", 20000)."\n", $formatter->formatAndWrap("\e[31mfoo\n".str_repeat("\e[0m", 20000), 5));
    }

    public function testFormatAndWrapWithWideCharacters()
    {
        $formatter = new OutputFormatter(true);

        $this->assertSame("字字\n字字\n字", $formatter->formatAndWrap('字字字字字', 4));
        $this->assertSame("ab\n字字\nc", $formatter->formatAndWrap('ab 字字 c', 4));
        $this->assertSame("\e[31m字\n字\e[0m", $formatter->formatAndWrap("\e[31m字字\e[0m", 3));
        $this->assertSame("a\e[37;41m\e[39;49m\n\e[37;41m字\e[39;49m\n\e[37;41m字\e[39;49m", $formatter->formatAndWrap('a<error>字字</error>', 2));

        $formatter = new OutputFormatter();

        $this->assertSame("a\n字\n字", $formatter->formatAndWrap('a<error>字字</error>', 2));
        $this->assertSame("a\n\e[31m字\n字", $formatter->formatAndWrap("a<error>\e[31m字字</error>", 2));
        $this->assertSame("字a\nbc", $formatter->formatAndWrap('字<error>abc</error>', 3));
        $this->assertSame("a\u{200B}字字", $formatter->formatAndWrap("a\u{200B}字字", 5));
        $this->assertSame("a字\u{200B}\u{200B}", $formatter->formatAndWrap("a<error>字\u{200B}\u{200B}</error>", 3));
    }

    public function testFormatAndWrapWithWindowsLineEndings()
    {
        $formatter = new OutputFormatter(true);

        $this->assertSame("Lorem\nipsum\r\ndolor\nsit\namet", $formatter->formatAndWrap("Lorem ipsum\r\ndolor sit amet", 5));
        $this->assertSame("\e[32mLorem\e[39m\n\e[32mipsum\e[39m\r\n\e[32mdolor\e[39m", $formatter->formatAndWrap("<info>Lorem ipsum\r\ndolor</info>", 5));
        $this->assertSame("\e[32mabc\e[39mdef\r\nghi", $formatter->formatAndWrap("<info>abc</info>def\r\nghi", 6));
        $this->assertSame("\e[32mabc\e[39mdef\ngh\r\nij", $formatter->formatAndWrap("<info>abc</info>defgh\r\nij", 6));
        $this->assertSame("foo\r\n", $formatter->formatAndWrap("foo \r\n", 3));
    }

    public function testFormatAndWrapWithEscapedCharacters()
    {
        $formatter = new OutputFormatter(true);

        $this->assertSame("ab<\ncd", $formatter->formatAndWrap('ab\<cd', 3));
        $this->assertSame("a<b>\nc", $formatter->formatAndWrap('a\<b\> c', 4));
        $this->assertSame("a字<\n字", $formatter->formatAndWrap('a字\<字', 4));
    }

    public function testFormatAndWrap()
    {
        $formatter = new OutputFormatter(true);

        $this->assertSame("fo\no\e[37;41mb\e[39;49m\n\e[37;41mar\e[39;49m\nba\nz", $formatter->formatAndWrap('foo<error>bar</error> baz', 2));
        $this->assertSame("pr\ne \e[37;41m\e[39;49m\n\e[37;41mfo\e[39;49m\n\e[37;41mo\e[39;49m\n\e[37;41mba\e[39;49m\n\e[37;41mr\e[39;49m\n\e[37;41mba\e[39;49m\n\e[37;41mz\e[39;49m \npo\nst", $formatter->formatAndWrap('pre <error>foo bar baz</error> post', 2));
        $this->assertSame("pre\e[37;41m\e[39;49m\n\e[37;41mfoo\e[39;49m\n\e[37;41mbar\e[39;49m\n\e[37;41mbaz\e[39;49m\npos\nt", $formatter->formatAndWrap('pre <error>foo bar baz</error> post', 3));
        $this->assertSame("pre \e[37;41m\e[39;49m\n\e[37;41mfoo\e[39;49m\n\e[37;41mbar\e[39;49m\n\e[37;41mbaz\e[39;49m \npost", $formatter->formatAndWrap('pre <error>foo bar baz</error> post', 4));
        $this->assertSame("pre \e[37;41mf\e[39;49m\n\e[37;41moo\e[39;49m\n\e[37;41mbar\e[39;49m\n\e[37;41mbaz\e[39;49m p\nost", $formatter->formatAndWrap('pre <error>foo bar baz</error> post', 5));
        $this->assertSame("Lore\nm \e[37;41mip\e[39;49m\n\e[37;41msum\e[39;49m \ndolo\nr \e[32msi\e[39m\n\e[32mt\e[39m am\net", $formatter->formatAndWrap('Lorem <error>ipsum</error> dolor <info>sit</info> amet', 4));
        $this->assertSame("Lorem \e[37;41mip\e[39;49m\n\e[37;41msum\e[39;49m dolo\nr \e[32msit\e[39m am\net", $formatter->formatAndWrap('Lorem <error>ipsum</error> dolor <info>sit</info> amet', 8));
        $this->assertSame("Lorem \e[37;41mipsum\e[39;49m dolor \e[32m\e[39m\n\e[32msit\e[39m, \e[37;41mamet\e[39;49m et \e[32mlauda\e[39m\n\e[32mntium\e[39m architecto", $formatter->formatAndWrap('Lorem <error>ipsum</error> dolor <info>sit</info>, <error>amet</error> et <info>laudantium</info> architecto', 18));
        $this->assertSame("\e[37;41mnon-empty-array\e[39;49m\e[37;41m<mixed, mixed>\e[39;49m given.\n🪪  argument.type", $formatter->formatAndWrap("<error>non-empty-array<mixed, mixed></error> given.\n🪪  argument.type", 38));
        $this->assertSame("Usuário <strong>{{user_name}}</strong> não é válid\no.", $formatter->formatAndWrap('Usuário <strong>{{user_name}}</strong> não é válido.', 50));
        $this->assertSame("foo\e[37;41mb\e[39;49m\n\e[37;41mar\e[39;49mbaz", $formatter->formatAndWrap("foo<error>b\nar</error>baz", 7));
        $this->assertSame("foo\e[37;41mbar\e[39;49mbaz\nnewline", $formatter->formatAndWrap("foo<error>bar</error>baz\nnewline", 11));
        $this->assertSame("foobarbaz\n\e[37;41mnewline\e[39;49m", $formatter->formatAndWrap("foobarbaz\n<error>newline</error>", 11));
        $this->assertSame("foobar\e[37;41mbaz\e[39;49m\n\e[37;41mnewline\e[39;49m", $formatter->formatAndWrap("foobar<error>baz\nnewline</error>", 11));
        $this->assertSame("foobar\e[37;41mbazne\e[39;49m\n\e[37;41mwline\e[39;49m", $formatter->formatAndWrap("foobar<error>bazne\nwline</error>", 11));
        $this->assertSame("foobar\e[37;41mbazne\e[39;49m\n\e[37;41mw\e[39;49m\n\e[37;41mline\e[39;49m", $formatter->formatAndWrap("foobar<error>baznew\nline</error>", 11));
        $this->assertSame("\e[37;41m👩‍🌾\e[39;49m", $formatter->formatAndWrap('<error>👩‍🌾</error>', 1));
        $this->assertSame("foo\n\n\e[37;41mbar\e[39;49m", $formatter->formatAndWrap("foo\n\n<error>bar</error>", 11));
        $this->assertSame("\e[37;41mfoo\e[39;49m\n\e[37;41mbar\e[39;49m", $formatter->formatAndWrap("<error>foo</error>\n<error>bar</error>", 11));
        $this->assertSame("\e[37;41mfoo\e[39;49m\n\e[37;41m\e[39;49mbar", $formatter->formatAndWrap("<error>foo\n</error>bar", 11));

        $formatter = new OutputFormatter();

        $this->assertSame("fo\nob\nar\nba\nz", $formatter->formatAndWrap('foo<error>bar</error> baz', 2));
        $this->assertSame("pr\ne \nfo\no\nba\nr\nba\nz \npo\nst", $formatter->formatAndWrap('pre <error>foo bar baz</error> post', 2));
        $this->assertSame("pre\nfoo\nbar\nbaz\npos\nt", $formatter->formatAndWrap('pre <error>foo bar baz</error> post', 3));
        $this->assertSame("pre \nfoo\nbar\nbaz \npost", $formatter->formatAndWrap('pre <error>foo bar baz</error> post', 4));
        $this->assertSame("pre f\noo\nbar\nbaz p\nost", $formatter->formatAndWrap('pre <error>foo bar baz</error> post', 5));
        $this->assertSame("Â rèälly\nlöng tîtlè\nthät cöüld\nnèêd\nmúltîplê\nlínès", $formatter->formatAndWrap('Â rèälly löng tîtlè thät cöüld nèêd múltîplê línès', 10));
        $this->assertSame("Â rèälly\nlöng tîtlè\nthät cöüld\nnèêd\nmúltîplê\n línès", $formatter->formatAndWrap("Â rèälly löng tîtlè thät cöüld nèêd múltîplê\n línès", 10));
        $this->assertSame('', $formatter->formatAndWrap(null, 5));
        $this->assertSame("non-empty-array<mixed, mixed> given.\n🪪  argument.type", $formatter->formatAndWrap("<error>non-empty-array<mixed, mixed></error> given.\n🪪  argument.type", 38));
        $this->assertSame("Usuário <strong>{{user_name}}</strong> não é válid\no.", $formatter->formatAndWrap('Usuário <strong>{{user_name}}</strong> não é válido.', 50));
        $this->assertSame("foob\narbaz", $formatter->formatAndWrap("foo<error>b\nar</error>baz", 7));
        $this->assertSame("foobarbaz\nnewline", $formatter->formatAndWrap("foo<error>bar</error>baz\nnewline", 11));
        $this->assertSame("foobarbaz\nnewline", $formatter->formatAndWrap("foobarbaz\n<error>newline</error>", 11));
        $this->assertSame("foobarbaz\nnewline", $formatter->formatAndWrap("foobar<error>baz\nnewline</error>", 11));
        $this->assertSame("foobarbazne\nwline", $formatter->formatAndWrap("foobar<error>bazne\nwline</error>", 11));
        $this->assertSame("foobarbazne\nw\nline", $formatter->formatAndWrap("foobar<error>baznew\nline</error>", 11));
        $this->assertSame('👩‍🌾', $formatter->formatAndWrap('👩‍🌾', 1));
        $this->assertSame("foo\n\nbar", $formatter->formatAndWrap("foo\n\n<error>bar</error>", 11));
        $this->assertSame("foo\nbar", $formatter->formatAndWrap("<error>foo</error>\n<error>bar</error>", 11));
        $this->assertSame("foo\n\nbar", $formatter->formatAndWrap("<error>foo\n</error>\nbar", 11));
        $this->assertSame("foo\n  bar", $formatter->formatAndWrap("foo\n<error>  bar</error>", 11));
        $this->assertSame("foobarbazne\n\nwline", $formatter->formatAndWrap("foobar<error>bazne</error>\n\nwline", 11));
        $this->assertSame("abcdef\n", $formatter->formatAndWrap('<info>abcdef</info> ', 6));
        $this->assertSame("abcdef\nx", $formatter->formatAndWrap('<info>abcdef</info> <comment> x</comment>', 6));
    }

    public function testFormatKeepsMultiByteContentUnchanged()
    {
        $formatter = new OutputFormatter();

        $this->assertSame("e\u{0301}x", $formatter->format("e<info>\u{0301}x</info>"));
        $this->assertSame("a\u{0301} e\u{0301}", $formatter->format("a\u{0301} <info>e\u{0301}</info>"));
        $this->assertSame('não é válido 日本語 😀', $formatter->format('não <info>é válido</info> 日本語 <comment>😀</comment>'));
    }
}

class TableCell
{
    public function __toString(): string
    {
        return '<info>some info</info>';
    }
}
