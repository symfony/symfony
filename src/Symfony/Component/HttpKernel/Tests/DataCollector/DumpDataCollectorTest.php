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
        $this->assertEquals($xDump, $dump);

        $this->assertStringMatchesFormat(
            'a:1:{i:0;a:5:{s:4:"data";O:39:"Symfony\Component\VarDumper\Cloner\Data":4:{s:45:"Symfony\Component\VarDumper\Cloner\Datadata";a:1:{i:0;a:1:{i:0;i:123;}}s:49:"Symfony\Component\VarDumper\Cloner\DatamaxDepth";i:%i;s:57:"Symfony\Component\VarDumper\Cloner\DatamaxItemsPerDepth";i:%i;s:54:"Symfony\Component\VarDumper\Cloner\DatauseRefHandles";i:%i;}s:4:"name";s:25:"DumpDataCollectorTest.php";s:4:"file";s:%a',
            str_replace("\0", '', $collector->serialize())
        );

        $this->assertSame(0, $collector->getDumpsCount());
        $this->assertSame('a:0:{}', $collector->serialize());
    }

    public function testFlush()
    {
        $data = new Data(array(array(456)));
        $collector = new DumpDataCollector();
        $collector->dump($data);
        $line = __LINE__ - 1;

        ob_start();
        $collector = null;
        $this->assertSame("DumpDataCollectorTest.php on line {$line}:\n456\n", ob_get_clean());
    }
}
