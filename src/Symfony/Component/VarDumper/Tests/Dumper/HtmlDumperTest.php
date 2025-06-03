<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Caster\ImgStub;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Tests\Fixtures\VirtualProperty;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class HtmlDumperTest extends TestCase
{
    public function testGet()
    {
        if (\ini_get('xdebug.file_link_format') || get_cfg_var('xdebug.file_link_format')) {
            $this->markTestSkipped('A custom file_link_format is defined.');
        }

        require __DIR__.'/../Fixtures/dumb-var.php';

        $dumper = new HtmlDumper('php://output');
        $dumper->setDumpHeader('<foo></foo>');
        $dumper->setDumpBoundaries('<bar>', '</bar>');
        $cloner = new VarCloner();
        $cloner->addCasters([
            ':stream' => function ($res, $a) {
                unset($a['uri'], $a['wrapper_data']);

                return $a;
            },
        ]);
        $data = $cloner->cloneVar($var);

        ob_start();
        $dumper->dump($data);
        $out = ob_get_clean();
        $out = preg_replace('/[ \t]+$/m', '', $out);
        $var['file'] = htmlspecialchars($var['file'], \ENT_QUOTES, 'UTF-8');
        $intMax = \PHP_INT_MAX;
        preg_match('/sf-dump-\d+/', $out, $dumpId);
        $dumpId = $dumpId[0];
        $res = (int) $var['res'];

        $this->assertStringMatchesFormat(
            <<<EOTXT
<foo></foo><bar><span class=sf-dump-note>array:25</span> [<samp data-depth=1 class=sf-dump-expanded>
  "<span class=sf-dump-key>number</span>" => <span class=sf-dump-num>1</span>
  <span class=sf-dump-key>0</span> => <a class=sf-dump-ref href=#{$dumpId}-ref01 title="2 occurrences">&amp;1</a> <span class=sf-dump-const>null</span>
  "<span class=sf-dump-key>const</span>" => <span class=sf-dump-num>1.1</span>
  <span class=sf-dump-key>1</span> => <span class=sf-dump-const>true</span>
  <span class=sf-dump-key>2</span> => <span class=sf-dump-const>false</span>
  <span class=sf-dump-key>3</span> => <span class=sf-dump-num>NAN</span>
  <span class=sf-dump-key>4</span> => <span class=sf-dump-num>INF</span>
  <span class=sf-dump-key>5</span> => <span class=sf-dump-num>-INF</span>
  <span class=sf-dump-key>6</span> => <span class=sf-dump-num>{$intMax}</span>
  "<span class=sf-dump-key>str</span>" => "<span class=sf-dump-str title="5 characters">d&%s;j&%s;<span class="sf-dump-default sf-dump-ns">\\n</span></span>"
  <span class=sf-dump-key>7</span> => b"""
    <span class=sf-dump-str title="11 binary or non-UTF-8 characters">&#233;<span class="sf-dump-default">\\x01</span>test<span class="sf-dump-default">\\t</span><span class="sf-dump-default sf-dump-ns">\\n</span></span>
    <span class=sf-dump-str title="11 binary or non-UTF-8 characters">ing</span>
    """
  "<span class=sf-dump-key>bo<span class=sf-dump-default>\\u{FEFF}</span>m</span>" => "<span class=sf-dump-str title="5 characters">te<span class=sf-dump-default>\\u{FEFF}</span>st</span>"
  "<span class=sf-dump-key>[]</span>" => []
  "<span class=sf-dump-key>res</span>" => <span class=sf-dump-note>stream resource</span> <a class=sf-dump-ref>@{$res}</a><samp data-depth=2 class=sf-dump-compact>
%A  <span class=sf-dump-meta>wrapper_type</span>: "<span class=sf-dump-str title="9 characters">plainfile</span>"
    <span class=sf-dump-meta>stream_type</span>: "<span class=sf-dump-str title="5 characters">STDIO</span>"
    <span class=sf-dump-meta>mode</span>: "<span class=sf-dump-str>r</span>"
    <span class=sf-dump-meta>unread_bytes</span>: <span class=sf-dump-num>0</span>
    <span class=sf-dump-meta>seekable</span>: <span class=sf-dump-const>true</span>
%A  <span class=sf-dump-meta>options</span>: []
  </samp>}
  "<span class=sf-dump-key>obj</span>" => <span class="sf-dump-note sf-dump-ellipsization" title="Symfony\Component\VarDumper\Tests\Fixture\DumbFoo
"><span class="sf-dump-ellipsis sf-dump-ellipsis-note">Symfony\Component\VarDumper\Tests\Fixture</span><span class="sf-dump-ellipsis sf-dump-ellipsis-note">\</span><span class="sf-dump-ellipsis-tail">DumbFoo</span></span> {<a class=sf-dump-ref href=#{$dumpId}-ref2%d title="2 occurrences">#%d</a><samp data-depth=2 id={$dumpId}-ref2%d class=sf-dump-compact>
    +<span class=sf-dump-public title="Public property">foo</span>: "<span class=sf-dump-str title="3 characters">foo</span>"
    +"<span class=sf-dump-public title="Runtime added dynamic property">bar</span>": "<span class=sf-dump-str title="3 characters">bar</span>"
  </samp>}
  "<span class=sf-dump-key>closure</span>" => <span class=sf-dump-note>Closure(\$a, ?PDO &amp;\$b = null)</span> {<a class=sf-dump-ref>#%d</a><samp data-depth=2 class=sf-dump-compact>
    <span class=sf-dump-meta>class</span>: "<span class="sf-dump-str sf-dump-ellipsization" title="Symfony\Component\VarDumper\Tests\Dumper\HtmlDumperTest
55 characters"><span class="sf-dump-ellipsis sf-dump-ellipsis-class">Symfony\Component\VarDumper\Tests\Dumper</span><span class="sf-dump-ellipsis sf-dump-ellipsis-class">\</span><span class="sf-dump-ellipsis-tail">HtmlDumperTest</span></span>"
    <span class=sf-dump-meta>this</span>: <span class="sf-dump-note sf-dump-ellipsization" title="Symfony\Component\VarDumper\Tests\Dumper\HtmlDumperTest
"><span class="sf-dump-ellipsis sf-dump-ellipsis-note">Symfony\Component\VarDumper\Tests\Dumper</span><span class="sf-dump-ellipsis sf-dump-ellipsis-note">\</span><span class="sf-dump-ellipsis-tail">HtmlDumperTest</span></span> {<a class=sf-dump-ref>#%d</a> &%s;}
    <span class=sf-dump-meta>file</span>: "<span class="sf-dump-str sf-dump-ellipsization" title="{$var['file']}
%d characters"><span class="sf-dump-ellipsis sf-dump-ellipsis-path">%s%eVarDumper</span><span class="sf-dump-ellipsis sf-dump-ellipsis-path">%e</span><span class="sf-dump-ellipsis-tail">Tests%eFixtures%edumb-var.php</span></span>"
    <span class=sf-dump-meta>line</span>: "<span class=sf-dump-str title="%d characters">{$var['line']} to {$var['line']}</span>"
  </samp>}
  "<span class=sf-dump-key>line</span>" => <span class=sf-dump-num>{$var['line']}</span>
  "<span class=sf-dump-key>nobj</span>" => <span class=sf-dump-note>array:1</span> [<samp data-depth=2 class=sf-dump-compact>
    <span class=sf-dump-index>0</span> => <a class=sf-dump-ref href=#{$dumpId}-ref03 title="2 occurrences">&amp;3</a> {<a class=sf-dump-ref href=#{$dumpId}-ref2%d title="3 occurrences">#%d</a>}
  </samp>]
  "<span class=sf-dump-key>recurs</span>" => <a class=sf-dump-ref href=#{$dumpId}-ref04 title="2 occurrences">&amp;4</a> <span class=sf-dump-note>array:1</span> [<samp data-depth=2 id={$dumpId}-ref04 class=sf-dump-compact>
    <span class=sf-dump-index>0</span> => <a class=sf-dump-ref href=#{$dumpId}-ref04 title="2 occurrences">&amp;4</a> <span class=sf-dump-note>array:1</span> [<a class=sf-dump-ref href=#{$dumpId}-ref04 title="2 occurrences">&amp;4</a>]
  </samp>]
  <span class=sf-dump-key>8</span> => <a class=sf-dump-ref href=#{$dumpId}-ref01 title="2 occurrences">&amp;1</a> <span class=sf-dump-const>null</span>
  "<span class=sf-dump-key>sobj</span>" => <span class="sf-dump-note sf-dump-ellipsization" title="Symfony\Component\VarDumper\Tests\Fixture\DumbFoo
"><span class="sf-dump-ellipsis sf-dump-ellipsis-note">Symfony\Component\VarDumper\Tests\Fixture</span><span class="sf-dump-ellipsis sf-dump-ellipsis-note">\</span><span class="sf-dump-ellipsis-tail">DumbFoo</span></span> {<a class=sf-dump-ref href=#{$dumpId}-ref2%d title="2 occurrences">#%d</a>}
  "<span class=sf-dump-key>snobj</span>" => <a class=sf-dump-ref href=#{$dumpId}-ref03 title="2 occurrences">&amp;3</a> {<a class=sf-dump-ref href=#{$dumpId}-ref2%d title="3 occurrences">#%d</a>}
  "<span class=sf-dump-key>snobj2</span>" => {<a class=sf-dump-ref href=#{$dumpId}-ref2%d title="3 occurrences">#%d</a>}
  "<span class=sf-dump-key>file</span>" => "<span class=sf-dump-str title="%d characters">{$var['file']}</span>"
  b"<span class=sf-dump-key>bin-key-&%s;</span>" => ""
</samp>]
</bar>

EOTXT
            ,

            $out
        );
    }

    /**
     * @requires PHP 8.4
     */
    public function testVirtualProperties()
    {
        $dumper = new HtmlDumper('php://output');
        $dumper->setDumpHeader('<foo></foo>');
        $dumper->setDumpBoundaries('<bar>', '</bar>');
        $cloner = new VarCloner();

        $data = $cloner->cloneVar(new VirtualProperty());
        $out = $dumper->dump($data, true);

        $this->assertStringMatchesFormat(<<<EODUMP
            <foo></foo><bar><span class=sf-dump-note>Symfony\Component\VarDumper\Tests\Fixtures\VirtualProperty</span> {<a class=sf-dump-ref>#%i</a><samp data-depth=1 class=sf-dump-expanded>
              +<span class=sf-dump-public title="Public property">firstName</span>: "<span class=sf-dump-str title="4 characters">John</span>"
              +<span class=sf-dump-public title="Public property">lastName</span>: "<span class=sf-dump-str title="3 characters">Doe</span>"
              +<span class=sf-dump-virtual><span class=sf-dump-public title="Public property">fullName</span></span>: <span class=sf-dump-virtual><span class=sf-dump-const title="Virtual property">~ string</span></span>
              -<span class=sf-dump-virtual><span class=sf-dump-private title="Private property defined in class:&#10;`Symfony\Component\VarDumper\Tests\Fixtures\VirtualProperty`">noType</span></span>: <span class=sf-dump-virtual><span class=sf-dump-const title="Virtual property">~</span></span>
            </samp>}
            </bar>
            EODUMP, $out);
    }

    public function testCharset()
    {
        $var = mb_convert_encoding('Словарь', 'CP1251', 'UTF-8');

        $dumper = new HtmlDumper('php://output', 'CP1251');
        $dumper->setDumpHeader('<foo></foo>');
        $dumper->setDumpBoundaries('<bar>', '</bar>');
        $cloner = new VarCloner();

        $data = $cloner->cloneVar($var);
        $out = $dumper->dump($data, true);

        $this->assertStringMatchesFormat(
            <<<'EOTXT'
<foo></foo><bar>b"<span class=sf-dump-str title="7 binary or non-UTF-8 characters">&#1057;&#1083;&#1086;&#1074;&#1072;&#1088;&#1100;</span>"
</bar>

EOTXT
            ,
            $out
        );
    }

    public function testAppend()
    {
        $out = fopen('php://memory', 'r+');

        $dumper = new HtmlDumper();
        $dumper->setDumpHeader('<foo></foo>');
        $dumper->setDumpBoundaries('<bar>', '</bar>');
        $cloner = new VarCloner();

        $dumper->dump($cloner->cloneVar(123), $out);
        $dumper->dump($cloner->cloneVar(456), $out);

        $out = stream_get_contents($out, -1, 0);

        $this->assertSame(<<<'EOTXT'
<foo></foo><bar><span class=sf-dump-num>123</span>
</bar>
<bar><span class=sf-dump-num>456</span>
</bar>

EOTXT
            ,
            $out
        );
    }

    /**
     * @dataProvider varToDumpProvider
     */
    public function testDumpString($var, $needle)
    {
        $dumper = new HtmlDumper();
        $cloner = new VarCloner();

        ob_start();
        $dumper->dump($cloner->cloneVar($var));
        $out = ob_get_clean();

        $this->assertStringContainsString($needle, $out);
    }

    public static function varToDumpProvider()
    {
        return [
            [['dummy' => new ImgStub('dummy', 'img/png', '100em')], '<img src="data:img/png;base64,ZHVtbXk=" />'],
            ['foo', '<span class=sf-dump-str title="3 characters">foo</span>'],
        ];
    }
}
