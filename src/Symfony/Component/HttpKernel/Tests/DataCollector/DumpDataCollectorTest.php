<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DumpDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testDump()
    {
        $data = new Data(array(array(123)));

        $collector = new DumpDataCollector();

        $this->assertSame('dump', $collector->getName());

        $collector->dump($data);
        $line = __LINE__ - 1;
        $this->assertSame(1, $collector->getDumpsCount());

        $dump = $collector->getDumps('html');
        $this->assertTrue(isset($dump[0]['data']));
        $dump[0]['data'] = preg_replace('/^.*?<pre/', '<pre', $dump[0]['data']);
        $dump[0]['data'] = preg_replace('/sf-dump-\d+/', 'sf-dump', $dump[0]['data']);

        $xDump = array(
            array(
                'data' => "<pre class=sf-dump id=sf-dump data-indent-pad=\"  \"><span class=sf-dump-num>123</span>\n</pre><script>Sfdump(\"sf-dump\")</script>\n",
                'name' => 'DumpDataCollectorTest.php',
                'file' => __FILE__,
                'line' => $line,
                'fileExcerpt' => false,
            ),
        );
        $this->assertSame($xDump, $dump);

        $this->assertStringMatchesFormat(
            'a:1:{i:0;a:5:{s:4:"data";O:39:"Symfony\Component\VarDumper\Cloner\Data":4:{s:45:"Symfony\Component\VarDumper\Cloner\Datadata";a:1:{i:0;a:1:{i:0;i:123;}}s:49:"Symfony\Component\VarDumper\Cloner\DatamaxDepth";i:%i;s:57:"Symfony\Component\VarDumper\Cloner\DatamaxItemsPerDepth";i:%i;s:54:"Symfony\Component\VarDumper\Cloner\DatauseRefHandles";i:%i;}s:4:"name";s:25:"DumpDataCollectorTest.php";s:4:"file";s:%a',
            str_replace("\0", '', $collector->serialize())
        );

        $this->assertSame(0, $collector->getDumpsCount());
        $this->assertSame('a:0:{}', $collector->serialize());
    }

    public function testCollectDefault()
    {
        $data = new Data(array(array(123)));

        $collector = new DumpDataCollector();

        $collector->dump($data);
        $line = __LINE__ - 1;

        ob_start();
        $collector->collect(new Request(), new Response());
        $output = ob_get_clean();

        if (PHP_VERSION_ID >= 50400) {
            $this->assertSame("DumpDataCollectorTest.php on line {$line}:\n123\n", $output);
        } else {
            $this->assertSame("\"DumpDataCollectorTest.php on line {$line}:\"\n123\n", $output);
        }
        $this->assertSame(1, $collector->getDumpsCount());
        $collector->serialize();
    }

    public function testCollectHtml()
    {
        $data = new Data(array(array(123)));

        $collector = new DumpDataCollector(null, 'test://%f:%l');

        $collector->dump($data);
        $line = __LINE__ - 1;
        $file = __FILE__;
        if (PHP_VERSION_ID >= 50400) {
            $xOutput = <<<EOTXT
 <pre class=sf-dump id=sf-dump data-indent-pad="  "><a href="test://{$file}:{$line}" title="{$file}"><span class=sf-dump-meta>DumpDataCollectorTest.php</span></a> on line <span class=sf-dump-meta>{$line}</span>:
<span class=sf-dump-num>123</span>
</pre>

EOTXT;
        } else {
            $len = strlen("DumpDataCollectorTest.php on line {$line}:");
            $xOutput = <<<EOTXT
 <pre class=sf-dump id=sf-dump data-indent-pad="  ">"<span class=sf-dump-str title="{$len} characters">DumpDataCollectorTest.php on line {$line}:</span>"
</pre>
<pre class=sf-dump id=sf-dump data-indent-pad="  "><span class=sf-dump-num>123</span>
</pre>

EOTXT;
        }

        ob_start();
        $response = new Response();
        $response->headers->set('Content-Type', 'text/html');
        $collector->collect(new Request(), $response);
        $output = ob_get_clean();
        $output = preg_replace('#<(script|style).*?</\1>#s', '', $output);
        $output = preg_replace('/sf-dump-\d+/', 'sf-dump', $output);

        $this->assertSame($xOutput, $output);
        $this->assertSame(1, $collector->getDumpsCount());
        $collector->serialize();
    }

    public function testFlush()
    {
        $data = new Data(array(array(456)));
        $collector = new DumpDataCollector();
        $collector->dump($data);
        $line = __LINE__ - 1;

        ob_start();
        $collector = null;
        if (PHP_VERSION_ID >= 50400) {
            $this->assertSame("DumpDataCollectorTest.php on line {$line}:\n456\n", ob_get_clean());
        } else {
            $this->assertSame("\"DumpDataCollectorTest.php on line {$line}:\"\n456\n", ob_get_clean());
        }
    }
}
