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
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * DumpDataCollectorTest
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DumpDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testDump()
    {
        $data = new Data(array(array(123)));

        $collector = new DumpDataCollector();

        $this->assertSame('dump', $collector->getName());

        $collector->dump($data); $line = __LINE__;
        $this->assertSame(1, $collector->getDumpsCount());

        $xDump = array(
            array(
              'data' => "<!DOCTYPE html><style> pre.sf-dump { background-color: #300a24; white-space: pre; line-height: 1.2em; color: #eee8d5; font-family: monospace, sans-serif; padding: 5px; } .sf-dump span { display: inline; }a.sf-dump-ref {color:#444444}span.sf-dump-num {font-weight:bold;color:#0087FF}span.sf-dump-const {font-weight:bold;color:#0087FF}span.sf-dump-str {font-weight:bold;color:#00D7FF}span.sf-dump-cchr {font-style: italic}span.sf-dump-note {color:#D7AF00}span.sf-dump-ref {color:#444444}span.sf-dump-public {color:#008700}span.sf-dump-protected {color:#D75F00}span.sf-dump-private {color:#D70000}span.sf-dump-meta {color:#005FFF}</style><pre class=sf-dump><span class=sf-dump-0><span class=sf-dump-num>123</span>\n</pre>\n",
              'name' => 'DumpDataCollectorTest.php',
              'file' => __FILE__,
              'line' => $line,
              'fileExcerpt' => false,
            ),
        );
        $this->assertSame($xDump, $collector->getDumps('html'));

        $this->assertStringStartsWith(
            'a:1:{i:0;a:5:{s:4:"data";O:39:"Symfony\Component\VarDumper\Cloner\Data":3:{s:45:"Symfony\Component\VarDumper\Cloner\Datadata";a:1:{i:0;a:1:{i:0;i:123;}}s:49:"Symfony\Component\VarDumper\Cloner\DatamaxDepth";i:-1;s:57:"Symfony\Component\VarDumper\Cloner\DatamaxItemsPerDepth";i:-1;}s:4:"name";s:25:"DumpDataCollectorTest.php";s:4:"file";s:',
            str_replace("\0", '', $collector->serialize())
        );

        $this->assertSame(0, $collector->getDumpsCount());
        $this->assertSame('a:0:{}', $collector->serialize());
    }

    public function testFlush()
    {
        $data = new Data(array(array(456)));
        $collector = new DumpDataCollector();
        $collector->dump($data); $line = __LINE__;

        ob_start();
        $collector = null;
        $this->assertSame("DumpDataCollectorTest.php on line {$line}:\n456\n", ob_get_clean());
    }
}
