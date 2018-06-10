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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\ServerDumper;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DumpDataCollectorTest extends TestCase
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
        $this->assertArrayHasKey('data', $dump[0]);
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
        $this->assertEquals($xDump, $dump);

        $this->assertStringMatchesFormat('a:3:{i:0;a:5:{s:4:"data";%c:39:"Symfony\Component\VarDumper\Cloner\Data":%a', $collector->serialize());
        $this->assertSame(0, $collector->getDumpsCount());
        $this->assertSame('a:2:{i:0;b:0;i:1;s:5:"UTF-8";}', $collector->serialize());
    }

    public function testDumpWithServerDumper()
    {
        $data = new Data(array(array(123)));

        // Server is up, server dumper is used
        $serverDumper = $this->getMockBuilder(ServerDumper::class)->disableOriginalConstructor()->getMock();
        $serverDumper->expects($this->once())->method('dump');

        $collector = new DumpDataCollector(null, null, null, null, $serverDumper);
        $collector->dump($data);

        // Collect doesn't re-trigger dump
        ob_start();
        $collector->collect(new Request(), new Response());
        $this->assertEmpty(ob_get_clean());
        $this->assertStringMatchesFormat('a:3:{i:0;a:5:{s:4:"data";%c:39:"Symfony\Component\VarDumper\Cloner\Data":%a', $collector->serialize());
    }

    public function testCollectDefault()
    {
        $data = new Data(array(array(123)));

        $collector = new DumpDataCollector();

        $collector->dump($data);
        $line = __LINE__ - 1;

        ob_start();
        $collector->collect(new Request(), new Response());
        $output = preg_replace("/\033\[[^m]*m/", '', ob_get_clean());

        $this->assertSame("DumpDataCollectorTest.php on line {$line}:\n123\n", $output);
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
        $xOutput = <<<EOTXT
<pre class=sf-dump id=sf-dump data-indent-pad="  "><a href="test://{$file}:{$line}" title="{$file}"><span class=sf-dump-meta>DumpDataCollectorTest.php</span></a> on line <span class=sf-dump-meta>{$line}</span>:
<span class=sf-dump-num>123</span>
</pre>
EOTXT;

        ob_start();
        $response = new Response();
        $response->headers->set('Content-Type', 'text/html');
        $collector->collect(new Request(), $response);
        $output = ob_get_clean();
        $output = preg_replace('#<(script|style).*?</\1>#s', '', $output);
        $output = preg_replace('/sf-dump-\d+/', 'sf-dump', $output);

        $this->assertSame($xOutput, trim($output));
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
        $collector->__destruct();
        $output = preg_replace("/\033\[[^m]*m/", '', ob_get_clean());
        $this->assertSame("DumpDataCollectorTest.php on line {$line}:\n456\n", $output);
    }

    public function testFlushNothingWhenDataDumperIsProvided()
    {
        $data = new Data(array(array(456)));
        $dumper = new CliDumper('php://output');
        $collector = new DumpDataCollector(null, null, null, null, $dumper);

        ob_start();
        $collector->dump($data);
        $line = __LINE__ - 1;
        $output = preg_replace("/\033\[[^m]*m/", '', ob_get_clean());
        if (\PHP_VERSION_ID >= 50400) {
            $this->assertSame("DumpDataCollectorTest.php on line {$line}:\n456\n", $output);
        } else {
            $this->assertSame("\"DumpDataCollectorTest.php on line {$line}:\"\n456\n", $output);
        }

        ob_start();
        $collector->__destruct();
        $this->assertEmpty(ob_get_clean());
    }
}
