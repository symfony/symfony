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

use Symfony\Component\VarDumper\Cloner\PhpCloner;
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
        $cloner = new PhpCloner();
        $data = $cloner->cloneVar($var);

        ob_start();
        $dumper->dump($data);
        $out = ob_get_clean();
        $closureLabel = PHP_VERSION_ID >= 50400 ? 'public method' : 'function';
        $out = preg_replace('/[ \t]+$/m', '', $out);
        $var['file'] = htmlspecialchars($var['file'], ENT_QUOTES, 'UTF-8');

        $this->assertSame(
            <<<EOTXT
<!DOCTYPE html><style>
a.sf-dump-ref {color:#444444}
span.sf-dump-num {font-weight:bold;color:#0087FF}
span.sf-dump-const {font-weight:bold;color:#0087FF}
span.sf-dump-str {font-weight:bold;color:#00D7FF}
span.sf-dump-cchr {font-style: italic}
span.sf-dump-note {color:#D7AF00}
span.sf-dump-ref {color:#444444}
span.sf-dump-public {color:#008700}
span.sf-dump-protected {color:#D75F00}
span.sf-dump-private {color:#D70000}
span.sf-dump-meta {color:#005FFF}
</style>

<pre class=sf-dump style=white-space:pre><span class=sf-dump-0><span class=sf-dump-note>array:25</span> [
  <span class=sf-dump-1>"<span class=sf-dump-meta>number</span>" => <span class=sf-dump-num>1</span>
  <span class=sf-dump-meta>0</span> => <span class=sf-dump-const>null</span> <a class=sf-dump-ref name="sf-dump-ref1">#1</a>
  "<span class=sf-dump-meta>const</span>" => <span class=sf-dump-num>1.1</span>
  <span class=sf-dump-meta>1</span> => <span class=sf-dump-const>true</span>
  <span class=sf-dump-meta>2</span> => <span class=sf-dump-const>false</span>
  <span class=sf-dump-meta>3</span> => <span class=sf-dump-num>NAN</span>
  <span class=sf-dump-meta>4</span> => <span class=sf-dump-num>INF</span>
  <span class=sf-dump-meta>5</span> => <span class=sf-dump-num>-INF</span>
  <span class=sf-dump-meta>6</span> => <span class=sf-dump-num>9223372036854775807</span>
  "<span class=sf-dump-meta>str</span>" => "<span class=sf-dump-str>déjà</span>"
  <span class=sf-dump-meta>7</span> => b"<span class=sf-dump-str>é</span>"
  "<span class=sf-dump-meta>[]</span>" => []
  "<span class=sf-dump-meta>res</span>" => resource:<span class=sf-dump-note>stream</span> {
    <span class=sf-dump-2><span class=sf-dump-meta>wrapper_type</span>: "<span class=sf-dump-str>plainfile</span>"
    <span class=sf-dump-meta>stream_type</span>: "<span class=sf-dump-str>dir</span>"
    <span class=sf-dump-meta>mode</span>: "<span class=sf-dump-str>r</span>"
    <span class=sf-dump-meta>unread_bytes</span>: <span class=sf-dump-num>0</span>
    <span class=sf-dump-meta>seekable</span>: <span class=sf-dump-const>true</span>
    <span class=sf-dump-meta>timed_out</span>: <span class=sf-dump-const>false</span>
    <span class=sf-dump-meta>blocked</span>: <span class=sf-dump-const>true</span>
    <span class=sf-dump-meta>eof</span>: <span class=sf-dump-const>false</span>
    <span class=sf-dump-meta>options</span>: []
  </span>}
  <span class=sf-dump-meta>8</span> => resource:<span class=sf-dump-note>Unknown</span> {}
  "<span class=sf-dump-meta>obj</span>" => <span class=sf-dump-note>Symfony\Component\VarDumper\Tests\Fixture\DumbFoo</span> { <a class=sf-dump-ref name="sf-dump-ref2">#2</a>
    <span class=sf-dump-2><span class=sf-dump-public>foo</span>: "<span class=sf-dump-str>foo</span>"
    "<span class=sf-dump-public>bar</span>": "<span class=sf-dump-str>bar</span>"
  </span>}
  "<span class=sf-dump-meta>closure</span>" => <span class=sf-dump-note>Closure</span> {
    <span class=sf-dump-2><span class=sf-dump-meta>reflection</span>: """
      <span class=sf-dump-str>Closure [ &lt;user&gt; {$closureLabel} Symfony\Component\VarDumper\Tests\Fixture\{closure} ] {</span>
      <span class=sf-dump-str>  @@ {$var['file']} {$var['line']} - {$var['line']}</span>

      <span class=sf-dump-str>  - Parameters [2] {</span>
      <span class=sf-dump-str>    Parameter #0 [ &lt;required&gt; \$a ]</span>
      <span class=sf-dump-str>    Parameter #1 [ &lt;optional&gt; PDO or NULL &amp;\$b = NULL ]</span>
      <span class=sf-dump-str>  }</span>
      <span class=sf-dump-str>}</span>
      """
  </span>}
  "<span class=sf-dump-meta>line</span>" => <span class=sf-dump-num>{$var['line']}</span>
  "<span class=sf-dump-meta>nobj</span>" => <span class=sf-dump-note>array:1</span> [
    <span class=sf-dump-2><span class=sf-dump-meta>0</span> => {} <a class=sf-dump-ref name="sf-dump-ref3">#3</a>
  </span>]
  "<span class=sf-dump-meta>recurs</span>" => <span class=sf-dump-note>array:1</span> [ <a class=sf-dump-ref name="sf-dump-ref4">#4</a>
    <span class=sf-dump-2><span class=sf-dump-meta>0</span> => <a class=sf-dump-ref href="#sf-dump-ref4">&4</a> <span class=sf-dump-note>array:1</span> [<a class=sf-dump-ref href="#sf-dump-ref4">@4</a>]
  </span>]
  <span class=sf-dump-meta>9</span> => <a class=sf-dump-ref href="#sf-dump-ref1">&1</a> <span class=sf-dump-const>null</span>
  "<span class=sf-dump-meta>sobj</span>" => <span class=sf-dump-note>Symfony\Component\VarDumper\Tests\Fixture\DumbFoo</span> {<a class=sf-dump-ref href="#sf-dump-ref2">@2</a>}
  "<span class=sf-dump-meta>snobj</span>" => <a class=sf-dump-ref href="#sf-dump-ref3">&3</a> {<a class=sf-dump-ref href="#sf-dump-ref3">@3</a>}
  "<span class=sf-dump-meta>snobj2</span>" => {<a class=sf-dump-ref href="#sf-dump-ref3">@3</a>}
  "<span class=sf-dump-meta>file</span>" => "<span class=sf-dump-str>{$var['file']}</span>"
  b"<span class=sf-dump-meta>bin-key-é</span>" => ""
</span>]
</pre>

EOTXT
            ,

            $out
        );
    }
}
