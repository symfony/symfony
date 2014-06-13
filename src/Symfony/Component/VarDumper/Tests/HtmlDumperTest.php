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
a.sf-var-debug-ref {color:#444444}
span.sf-var-debug-num {font-weight:bold;color:#0087FF}
span.sf-var-debug-const {font-weight:bold;color:#0087FF}
span.sf-var-debug-str {font-weight:bold;color:#00D7FF}
span.sf-var-debug-cchr {font-style: italic}
span.sf-var-debug-note {color:#D7AF00}
span.sf-var-debug-ref {color:#444444}
span.sf-var-debug-public {color:#008700}
span.sf-var-debug-protected {color:#D75F00}
span.sf-var-debug-private {color:#D70000}
span.sf-var-debug-meta {color:#005FFF}
</style>

<pre class=sf-var-debug style=white-space:pre><span class=sf-var-debug-0><span class=sf-var-debug-note>array:25</span> [
  <span class=sf-var-debug-1>"<span class=sf-var-debug-meta>number</span>" => <span class=sf-var-debug-const>1</span>
  <span class=sf-var-debug-meta>0</span> => <span class=sf-var-debug-const>null</span> <a class=sf-var-debug-ref name="sf-var-debug-ref1">#1</a>
  "<span class=sf-var-debug-meta>const</span>" => <span class=sf-var-debug-num>1.1</span>
  <span class=sf-var-debug-meta>1</span> => <span class=sf-var-debug-const>true</span>
  <span class=sf-var-debug-meta>2</span> => <span class=sf-var-debug-const>false</span>
  <span class=sf-var-debug-meta>3</span> => <span class=sf-var-debug-num>NAN</span>
  <span class=sf-var-debug-meta>4</span> => <span class=sf-var-debug-num>INF</span>
  <span class=sf-var-debug-meta>5</span> => <span class=sf-var-debug-num>-INF</span>
  <span class=sf-var-debug-meta>6</span> => <span class=sf-var-debug-const>9223372036854775807</span>
  "<span class=sf-var-debug-meta>str</span>" => "<span class=sf-var-debug-str>déjà</span>"
  <span class=sf-var-debug-meta>7</span> => b"<span class=sf-var-debug-str>é</span>"
  "<span class=sf-var-debug-meta>[]</span>" => []
  "<span class=sf-var-debug-meta>res</span>" => resource:<span class=sf-var-debug-note>stream</span> {
    <span class=sf-var-debug-2><span class=sf-var-debug-meta>wrapper_type</span>: "<span class=sf-var-debug-str>plainfile</span>"
    <span class=sf-var-debug-meta>stream_type</span>: "<span class=sf-var-debug-str>dir</span>"
    <span class=sf-var-debug-meta>mode</span>: "<span class=sf-var-debug-str>r</span>"
    <span class=sf-var-debug-meta>unread_bytes</span>: <span class=sf-var-debug-const>0</span>
    <span class=sf-var-debug-meta>seekable</span>: <span class=sf-var-debug-const>true</span>
    <span class=sf-var-debug-meta>timed_out</span>: <span class=sf-var-debug-const>false</span>
    <span class=sf-var-debug-meta>blocked</span>: <span class=sf-var-debug-const>true</span>
    <span class=sf-var-debug-meta>eof</span>: <span class=sf-var-debug-const>false</span>
    <span class=sf-var-debug-meta>options</span>: []
  </span>}
  <span class=sf-var-debug-meta>8</span> => resource:<span class=sf-var-debug-note>Unknown</span> {}
  "<span class=sf-var-debug-meta>obj</span>" => <span class=sf-var-debug-note>Symfony\Component\VarDumper\Tests\Fixture\DumbFoo</span> { <a class=sf-var-debug-ref name="sf-var-debug-ref2">#2</a>
    <span class=sf-var-debug-2><span class=sf-var-debug-public>foo</span>: "<span class=sf-var-debug-str>foo</span>"
    "<span class=sf-var-debug-public>bar</span>": "<span class=sf-var-debug-str>bar</span>"
  </span>}
  "<span class=sf-var-debug-meta>closure</span>" => <span class=sf-var-debug-note>Closure</span> {
    <span class=sf-var-debug-2><span class=sf-var-debug-meta>reflection</span>: """
      <span class=sf-var-debug-str>Closure [ &lt;user&gt; {$closureLabel} Symfony\Component\VarDumper\Tests\Fixture\{closure} ] {</span>
      <span class=sf-var-debug-str>  @@ {$var['file']} {$var['line']} - {$var['line']}</span>

      <span class=sf-var-debug-str>  - Parameters [2] {</span>
      <span class=sf-var-debug-str>    Parameter #0 [ &lt;required&gt; \$a ]</span>
      <span class=sf-var-debug-str>    Parameter #1 [ &lt;optional&gt; PDO or NULL &amp;\$b = NULL ]</span>
      <span class=sf-var-debug-str>  }</span>
      <span class=sf-var-debug-str>}</span>
      """
  </span>}
  "<span class=sf-var-debug-meta>line</span>" => <span class=sf-var-debug-const>{$var['line']}</span>
  "<span class=sf-var-debug-meta>nobj</span>" => <span class=sf-var-debug-note>array:1</span> [
    <span class=sf-var-debug-2><span class=sf-var-debug-meta>0</span> => {} <a class=sf-var-debug-ref name="sf-var-debug-ref3">#3</a>
  </span>]
  "<span class=sf-var-debug-meta>recurs</span>" => <span class=sf-var-debug-note>array:1</span> [ <a class=sf-var-debug-ref name="sf-var-debug-ref4">#4</a>
    <span class=sf-var-debug-2><span class=sf-var-debug-meta>0</span> => <span class=sf-var-debug-note>array:1</span> [<a class=sf-var-debug-ref href="#sf-var-debug-ref4">&4</a>]
  </span>]
  <span class=sf-var-debug-meta>9</span> => <span class=sf-var-debug-const>null</span> <a class=sf-var-debug-ref href="#sf-var-debug-ref1">&1</a>
  "<span class=sf-var-debug-meta>sobj</span>" => <span class=sf-var-debug-note>Symfony\Component\VarDumper\Tests\Fixture\DumbFoo</span> {<a class=sf-var-debug-ref href="#sf-var-debug-ref2">@2</a>}
  "<span class=sf-var-debug-meta>snobj</span>" => {<a class=sf-var-debug-ref href="#sf-var-debug-ref3">&3</a>}
  "<span class=sf-var-debug-meta>snobj2</span>" => {<a class=sf-var-debug-ref href="#sf-var-debug-ref3">@3</a>}
  "<span class=sf-var-debug-meta>file</span>" => "<span class=sf-var-debug-str>{$var['file']}</span>"
  b"<span class=sf-var-debug-meta>bin-key-é</span>" => ""
</span>]
</pre>

EOTXT
            ,

            $out
        );
    }
}
