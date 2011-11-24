<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Console\Formatter;

use Symfony\Component\Console\Formatter\OutputFormatter;

class FormatterStyleTest extends \PHPUnit_Framework_TestCase
{
    public function testBundledStyles()
    {
        $formatter = new OutputFormatter(true);

        $this->assertTrue($formatter->hasStyle('error'));
        $this->assertTrue($formatter->hasStyle('info'));
        $this->assertTrue($formatter->hasStyle('comment'));
        $this->assertTrue($formatter->hasStyle('question'));

        $this->assertEquals(
            "\033[37;41msome error\033[0m", $formatter->format('<error>some error</error>')
        );
        $this->assertEquals(
            "\033[32msome info\033[0m", $formatter->format('<info>some info</info>')
        );
        $this->assertEquals(
            "\033[33msome comment\033[0m", $formatter->format('<comment>some comment</comment>')
        );
        $this->assertEquals(
            "\033[30;46msome question\033[0m", $formatter->format('<question>some question</question>')
        );
    }

    public function testNestedStyles()
    {
        $formatter = new OutputFormatter(true);

        $this->assertEquals(
            "\033[37;41msome \033[32msome info\033[0m error\033[0m", $formatter->format('<error>some <info>some info</info> error</error>')
        );
    }

    public function testNewStyle()
    {
        $formatter = new OutputFormatter(true);

        $style = $this->getMockBuilder('Symfony\Component\Console\Formatter\OutputFormatterStyleInterface')->getMock();
        $formatter->setStyle('test', $style);

        $this->assertEquals($style, $formatter->getStyle('test'));
        $this->assertNotEquals($style, $formatter->getStyle('info'));

        $style
            ->expects($this->once())
            ->method('apply')
            ->will($this->returnValue('[STYLE_BEG]some custom msg[STYLE_END]'));

        $this->assertEquals("[STYLE_BEG]some custom msg[STYLE_END]", $formatter->format('<test>some custom msg</test>'));
    }

    public function testRedefineStyle()
    {
        $formatter = new OutputFormatter(true);

        $style = $this->getMockBuilder('Symfony\Component\Console\Formatter\OutputFormatterStyleInterface')
            ->getMock();
        $formatter->setStyle('info', $style);

        $style
            ->expects($this->once())
            ->method('apply')
            ->will($this->returnValue('[STYLE_BEG]some custom msg[STYLE_END]'));

        $this->assertEquals(
            "[STYLE_BEG]some custom msg[STYLE_END]", $formatter->format('<info>some custom msg</info>')
        );
    }

    public function testInlineStyle()
    {
        $formatter = new OutputFormatter(true);

        $this->assertEquals("\033[34;41msome text\033[0m", $formatter->format('<fg=blue;bg=red>some text</>'));
        $this->assertEquals("\033[34;41msome text\033[0m", $formatter->format('<fg=blue;bg=red>some text</fg=blue;bg=red>'));
    }

    public function testNotDecoratedFormatter()
    {
        $formatter = new OutputFormatter(false);

        $this->assertTrue($formatter->hasStyle('error'));
        $this->assertTrue($formatter->hasStyle('info'));
        $this->assertTrue($formatter->hasStyle('comment'));
        $this->assertTrue($formatter->hasStyle('question'));

        $this->assertEquals(
            "some error", $formatter->format('<error>some error</error>')
        );
        $this->assertEquals(
            "some info", $formatter->format('<info>some info</info>')
        );
        $this->assertEquals(
            "some comment", $formatter->format('<comment>some comment</comment>')
        );
        $this->assertEquals(
            "some question", $formatter->format('<question>some question</question>')
        );

        $formatter->setDecorated(true);

        $this->assertEquals(
            "\033[37;41msome error\033[0m", $formatter->format('<error>some error</error>')
        );
        $this->assertEquals(
            "\033[32msome info\033[0m", $formatter->format('<info>some info</info>')
        );
        $this->assertEquals(
            "\033[33msome comment\033[0m", $formatter->format('<comment>some comment</comment>')
        );
        $this->assertEquals(
            "\033[30;46msome question\033[0m", $formatter->format('<question>some question</question>')
        );
    }

    public function testContentWithLineBreaks()
    {
        $formatter = new OutputFormatter(true);

        $this->assertEquals(<<<EOF
\033[32m
some text\033[0m
EOF
            , $formatter->format(<<<EOF
<info>
some text</info>
EOF
        ));

        $this->assertEquals(<<<EOF
\033[32msome text
\033[0m
EOF
            , $formatter->format(<<<EOF
<info>some text
</info>
EOF
        ));

        $this->assertEquals(<<<EOF
\033[32m
some text
\033[0m
EOF
            , $formatter->format(<<<EOF
<info>
some text
</info>
EOF
        ));

        $this->assertEquals(<<<EOF
\033[32m
some text
more text
\033[0m
EOF
            , $formatter->format(<<<EOF
<info>
some text
more text
</info>
EOF
        ));
    }
}
