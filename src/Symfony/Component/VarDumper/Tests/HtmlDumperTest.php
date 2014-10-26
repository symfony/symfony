<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class HtmlDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testGet()
    {
        require __DIR__.'/Fixtures/dumb-var.php';

        $dumper = new HtmlDumper('php://output');
        $dumper->setColors(false);
        $dumper->setDumpHeader('<foo></foo>');
        $dumper->setDumpBoundaries('<bar>', '</bar>');
        $cloner = new VarCloner();
        $cloner->addCasters(array(
            ':stream' => function ($res, $a) {
                unset($a['uri']);

                return $a;
            },
        ));
        $data = $cloner->cloneVar($var);

        ob_start();
        $dumper->dump($data);
        $out = ob_get_clean();
        $closureLabel = PHP_VERSION_ID >= 50400 ? 'public method' : 'function';
        $out = preg_replace('/[ \t]+$/m', '', $out);
        $var['file'] = htmlspecialchars($var['file'], ENT_QUOTES, 'UTF-8');
        $intMax = PHP_INT_MAX;
        preg_match('/sf-dump-\d+/', $out, $dumpId);
        $dumpId = $dumpId[0];
        $res1 = (int) $var['res'];
        $res2 = (int) $var[8];

        $this->assertStringMatchesFormat(
            <<<EOTXT
<foo></foo><bar><span class=sf-dump-note>array:25</span> [<samp>
  "<span class=sf-dump-meta>number</span>" => <span class=sf-dump-num>1</span>
  <span class=sf-dump-meta>0</span> => <a class=sf-dump-ref href=#{$dumpId}-ref01 title="2 occurrences">&amp;1</a> <span class=sf-dump-const>null</span>
  "<span class=sf-dump-meta>const</span>" => <span class=sf-dump-num>1.1</span>
  <span class=sf-dump-meta>1</span> => <span class=sf-dump-const>true</span>
  <span class=sf-dump-meta>2</span> => <span class=sf-dump-const>false</span>
  <span class=sf-dump-meta>3</span> => <span class=sf-dump-num>NAN</span>
  <span class=sf-dump-meta>4</span> => <span class=sf-dump-num>INF</span>
  <span class=sf-dump-meta>5</span> => <span class=sf-dump-num>-INF</span>
  <span class=sf-dump-meta>6</span> => <span class=sf-dump-num>{$intMax}</span>
  "<span class=sf-dump-meta>str</span>" => "<span class=sf-dump-str title="4 characters">d&#233;j&#224;</span>"
  <span class=sf-dump-meta>7</span> => b"<span class=sf-dump-str title="2 binary or non-UTF-8 characters">&#233;<span class=sf-dump-cchr title=\\x00>@</span></span>"
  "<span class=sf-dump-meta>[]</span>" => []
  "<span class=sf-dump-meta>res</span>" => <abbr title="`stream` resource" class=sf-dump-note>:stream</abbr> {<a class=sf-dump-solo-ref>@{$res1}</a><samp>
    <span class=sf-dump-meta>wrapper_type</span>: "<span class=sf-dump-str title="9 characters">plainfile</span>"
    <span class=sf-dump-meta>stream_type</span>: "<span class=sf-dump-str title="5 characters">STDIO</span>"
    <span class=sf-dump-meta>mode</span>: "<span class=sf-dump-str>r</span>"
    <span class=sf-dump-meta>unread_bytes</span>: <span class=sf-dump-num>0</span>
    <span class=sf-dump-meta>seekable</span>: <span class=sf-dump-const>true</span>
    <span class=sf-dump-meta>timed_out</span>: <span class=sf-dump-const>false</span>
    <span class=sf-dump-meta>blocked</span>: <span class=sf-dump-const>true</span>
    <span class=sf-dump-meta>eof</span>: <span class=sf-dump-const>false</span>
    <span class=sf-dump-meta>options</span>: []
  </samp>}
  <span class=sf-dump-meta>8</span> => <abbr title="`Unknown` resource" class=sf-dump-note>:Unknown</abbr> {<a class=sf-dump-solo-ref>@{$res2}</a>}
  "<span class=sf-dump-meta>obj</span>" => <abbr title="Symfony\Component\VarDumper\Tests\Fixture\DumbFoo" class=sf-dump-note>DumbFoo</abbr> {<a class=sf-dump-ref href=#{$dumpId}-ref2%d title="2 occurrences">#%d</a><samp id={$dumpId}-ref2%d>
    <span class=sf-dump-public>foo</span>: "<span class=sf-dump-str title="3 characters">foo</span>"
    "<span class=sf-dump-public title="Runtime added dynamic property">bar</span>": "<span class=sf-dump-str title="3 characters">bar</span>"
  </samp>}
  "<span class=sf-dump-meta>closure</span>" => <span class=sf-dump-note>Closure</span> {<a class=sf-dump-solo-ref>#%d</a><samp>
    <span class=sf-dump-meta>reflection</span>: """
      <span class=sf-dump-str title="%d characters">Closure [ &lt;user&gt; {$closureLabel} Symfony\Component\VarDumper\Tests\Fixture\{closure} ] {</span>
      <span class=sf-dump-str title="%d characters">  @@ {$var['file']} {$var['line']} - {$var['line']}</span>

      <span class=sf-dump-str title="%d characters">  - Parameters [2] {</span>
      <span class=sf-dump-str title="%d characters">    Parameter #0 [ &lt;required&gt; \$a ]</span>
      <span class=sf-dump-str title="%d characters">    Parameter #1 [ &lt;optional&gt; PDO or NULL &amp;\$b = NULL ]</span>
      <span class=sf-dump-str title="%d characters">  }</span>
      <span class=sf-dump-str title="%d characters">}</span>
      """
  </samp>}
  "<span class=sf-dump-meta>line</span>" => <span class=sf-dump-num>{$var['line']}</span>
  "<span class=sf-dump-meta>nobj</span>" => <span class=sf-dump-note>array:1</span> [<samp>
    <span class=sf-dump-meta>0</span> => <a class=sf-dump-ref href=#{$dumpId}-ref03 title="2 occurrences">&amp;3</a> {<a class=sf-dump-ref href=#{$dumpId}-ref2%d title="3 occurrences">#%d</a>}
  </samp>]
  "<span class=sf-dump-meta>recurs</span>" => <a class=sf-dump-ref href=#{$dumpId}-ref04 title="2 occurrences">&amp;4</a> <span class=sf-dump-note>array:1</span> [<samp id={$dumpId}-ref04>
    <span class=sf-dump-meta>0</span> => <a class=sf-dump-ref href=#{$dumpId}-ref04 title="2 occurrences">&amp;4</a> <span class=sf-dump-note>array:1</span> [<a class=sf-dump-ref href=#{$dumpId}-ref04 title="2 occurrences">&amp;4</a>]
  </samp>]
  <span class=sf-dump-meta>9</span> => <a class=sf-dump-ref href=#{$dumpId}-ref01 title="2 occurrences">&amp;1</a> <span class=sf-dump-const>null</span>
  "<span class=sf-dump-meta>sobj</span>" => <abbr title="Symfony\Component\VarDumper\Tests\Fixture\DumbFoo" class=sf-dump-note>DumbFoo</abbr> {<a class=sf-dump-ref href=#{$dumpId}-ref2%d title="2 occurrences">#%d</a>}
  "<span class=sf-dump-meta>snobj</span>" => <a class=sf-dump-ref href=#{$dumpId}-ref03 title="2 occurrences">&amp;3</a> {<a class=sf-dump-ref href=#{$dumpId}-ref2%d title="3 occurrences">#%d</a>}
  "<span class=sf-dump-meta>snobj2</span>" => {<a class=sf-dump-ref href=#{$dumpId}-ref2%d title="3 occurrences">#%d</a>}
  "<span class=sf-dump-meta>file</span>" => "<span class=sf-dump-str title="%d characters">{$var['file']}</span>"
  b"<span class=sf-dump-meta>bin-key-&#233;</span>" => ""
</samp>]
</bar>

EOTXT
            ,

            $out
        );
    }
}
