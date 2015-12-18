<?php


namespace Symfony\Component\VarDumper\Tests\Dumper;


use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Dumper\TraceableDumper;
use Symfony\Component\VarDumper\Tests\Profiler\Mock\TwigTemplate;
use Symfony\Component\VarDumper\VarDumper;

class TraceableDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testDump()
    {
        $data = new Data(array(array(123)));

        $dumper = new TraceableDumper(new HtmlDumper(), new Stopwatch());

        ob_start();
        $dumper->dump($data);
        $output = ob_get_clean();
        $line = __LINE__ -2;

        if (PHP_VERSION_ID >= 50400) {
            $xOutput = $this->expectedOutput($line, 123);
        } else {
            $xOutput = $this->legacyOutput($line, 123);
        }

        $this->assertSame($xOutput, $this->cleanOutput($output));
        $this->assertCount(1, $dumper->getData());
    }

    public function testDumpExtensive()
    {
        $data = new Data(array(array(123)));

        $dumper = new TraceableDumper(new HtmlDumper());

        ob_start();
        VarDumper::setHandler(array($dumper, 'dump'));
        VarDumper::dump($data);
        $output = ob_get_clean();
        $line = __LINE__ -2;

        if (PHP_VERSION_ID >= 50400) {
            $xOutput = $this->expectedOutput($line, 123);
        } else {
            $xOutput = $this->legacyOutput($line, 123);
        }

        $this->assertSame($xOutput, $this->cleanOutput($output));
        $this->assertCount(1, $dumper->getData());
    }

    public function testDumpFromTwigTemplate()
    {

        $loader = $this->getMockBuilder('Twig_LoaderInterface')->getMock();

        $dumper = new TraceableDumper(new HtmlDumper(), null, null, null, $loader);

        VarDumper::setHandler(array($dumper, 'dump'));
        $src = '';
        for ($i = 1; $i < 100; $i++) {
            $src .= $i."\n";
        }
        $loader->expects($this->any())->method('getSource')->willReturn($src);

        $environment = $this->getMockBuilder('Twig_Environment')
            ->disableOriginalConstructor()
            ->getMock();

        $template = new TwigTemplate($environment, $dumper);
        ob_start();
        $template->display(array(123));

        $output = ob_get_clean();

        if (PHP_VERSION_ID >= 50400) {
            $xOutput = $this->expectedOutput(29, 123, 'num', 'Tests/Profiler/Mock/TwigTemplate.php', 'TwigTemplate.php');
        } else {
            $xOutput = $this->legacyOutput(29, 123, 'num', 'TwigTemplate.php');
        }

        $this->assertSame($xOutput, $this->cleanOutput($output));
        $this->assertCount(1, $dumper->getData());
    }

    public function testDumpCallUserFunction()
    {
        $dumper = new TraceableDumper(new HtmlDumper());
        VarDumper::setHandler(array($dumper, 'dump'));

        ob_start();
        testTraceableDumperTestDump(array(123));
        $output = ob_get_clean();
        $line = __LINE__ -2;

        if (PHP_VERSION_ID >= 50400) {
            $xOutput = $this->expectedOutput($line, 123);
        } else {
            $xOutput = $this->legacyOutput($line, 123);
        }

        $this->assertSame($xOutput, $this->cleanOutput($output));
        $this->assertCount(1, $dumper->getData());
    }

    public function testCollectHtml()
    {
        $dumper = new TraceableDumper(new HtmlDumper(), null, 'test://%f:%l');

        VarDumper::setHandler(array($dumper, 'dump'));

        ob_start();
        VarDumper::dump(new Data(array(array(123))));
        $output = ob_get_clean();

        $line = __LINE__ - 3;
        if (PHP_VERSION_ID >= 50400) {
            $xOutput = sprintf(
                "<pre class=sf-dump id=sf-dump data-indent-pad=\"  \"><a href=\"test://%s:%s\" title=\"%s\"><span class=sf-dump-meta>%s</span></a> on line <span class=sf-dump-meta>%s</span>:\n<span class=sf-dump-num>123</span>\n</pre>\n",
                __FILE__,
                $line,
                __FILE__,
                substr(__FILE__, strlen(__DIR__) - strlen(__FILE__)+1),
                $line
            );
        } else {
            $xOutput = $this->legacyOutput($line, 123);
        }

        $this->assertSame($xOutput, $this->cleanOutput($output, false));
        $this->assertCount(1, $dumper->getData());
    }

    public function testDescruction()
    {
        $data = new Data(array(array(123)));

        $traceableDumper = new TraceableDumper();

        ob_start();
        $traceableDumper->dump($data);
        $line = __LINE__ - 1;
        unset($traceableDumper);
        $output = ob_get_clean();


        if (PHP_VERSION_ID >= 50400) {
            $this->assertSame("TraceableDumperTest.php on line {$line}:\n123\n", $output);
        } else {
            $this->assertSame("\"TraceableDumperTest.php on line {$line}:\"\n123\n", $output);
        }
    }

    private function cleanOutput($output, $replacePath = true)
    {
        $output = preg_replace('#<(script|style).*?</\1>#s', '', $output);
        $output = preg_replace('/sf-dump-\d+/', 'sf-dump', $output);
        $output = preg_replace('/^.*?<pre/', '<pre', $output);
        $output = preg_replace('/<\/pre>.*$/', '</pre>',$output);
        if ( $replacePath ) {
            $output = preg_replace('/^.*Tests\/Dumper\/TraceableDumperTest.php\">/', '<pre class=sf-dump id=sf-dump data-indent-pad="  "><abbr title="Tests/Dumper/TraceableDumperTest.php">', $output);
            $output = preg_replace('/^.*Tests\/Profiler\/Mock\/TwigTemplate.php\">/', '<pre class=sf-dump id=sf-dump data-indent-pad="  "><abbr title="Tests/Profiler/Mock/TwigTemplate.php">', $output);
        }

        return $output;
    }

    private function legacyOutput($line, $value, $type = 'num', $file = 'TraceableDumperTest.php')
    {
        $len = strlen(sprintf("%s on line %s:", $file, $line));
        return sprintf(
            "<pre class=sf-dump id=sf-dump data-indent-pad=\"  \">\"<span class=sf-dump-str title=\"%s characters\">%s on line %s:</span>\"\n</pre>\n<pre class=sf-dump id=sf-dump data-indent-pad=\"  \"><span class=sf-dump-%s>%s</span>\n</pre>\n",
            $len,
            $file,
            $line,
            $type,
            $value
        );
    }


    private function expectedOutput($line, $value, $type = 'num', $path = 'Tests/Dumper/TraceableDumperTest.php', $file = 'TraceableDumperTest.php')
    {
        return sprintf(
            "<pre class=sf-dump id=sf-dump data-indent-pad=\"  \"><abbr title=\"%s\"><span class=sf-dump-meta>%s</span></abbr> on line <span class=sf-dump-meta>%s</span>:\n<span class=sf-dump-%s>%s</span>\n</pre>\n",
            $path,
            $file,
            $line,
            $type,
            $value
        );
    }
}

function testTraceableDumperTestDump($data) {
    VarDumper::dump(new Data(array($data)));
}