<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\DumpExtension;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class DumpExtensionTest extends TestCase
{
    /**
     * @dataProvider getDumpTags
     */
    public function testDumpTag($template, $debug, $expectedOutput, $expectedDumped)
    {
        $extension = new DumpExtension(new VarCloner());
        $twig = new Environment(new ArrayLoader(['template' => $template]), [
            'debug' => $debug,
            'cache' => false,
            'optimizations' => 0,
        ]);
        $twig->addExtension($extension);

        $dumped = null;
        $exception = null;
        $prevDumper = VarDumper::setHandler(function ($var) use (&$dumped) { $dumped = $var; });

        try {
            $this->assertEquals($expectedOutput, $twig->render('template'));
        } catch (\Exception $exception) {
        }

        VarDumper::setHandler($prevDumper);

        if (null !== $exception) {
            throw $exception;
        }

        $this->assertSame($expectedDumped, $dumped);
    }

    public function getDumpTags()
    {
        return [
            ['A{% dump %}B', true, 'AB', []],
            ['A{% set foo="bar"%}B{% dump %}C', true, 'ABC', ['foo' => 'bar']],
            ['A{% dump %}B', false, 'AB', null],
        ];
    }

    /**
     * @dataProvider getDumpArgs
     */
    public function testDump($context, $args, $expectedOutput, $debug = true)
    {
        $extension = new DumpExtension(new VarCloner());
        $twig = new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock(), [
            'debug' => $debug,
            'cache' => false,
            'optimizations' => 0,
        ]);

        array_unshift($args, $context);
        array_unshift($args, $twig);

        $dump = \call_user_func_array([$extension, 'dump'], $args);

        if ($debug) {
            $this->assertStringStartsWith('<script>', $dump);
            $dump = preg_replace('/^.*?<pre/', '<pre', $dump);
            $dump = preg_replace('/sf-dump-\d+/', 'sf-dump', $dump);
            $dump = preg_replace('/<samp [^>]++>/', '<samp>', $dump);
        }
        $this->assertEquals($expectedOutput, $dump);
    }

    public function getDumpArgs()
    {
        return [
            [[], [], '', false],
            [[], [], "<pre class=sf-dump id=sf-dump data-indent-pad=\"  \">[]\n</pre><script>Sfdump(\"sf-dump\")</script>\n"],
            [
                [],
                [123, 456],
                "<pre class=sf-dump id=sf-dump data-indent-pad=\"  \"><span class=sf-dump-num>123</span>\n</pre><script>Sfdump(\"sf-dump\")</script>\n"
                ."<pre class=sf-dump id=sf-dump data-indent-pad=\"  \"><span class=sf-dump-num>456</span>\n</pre><script>Sfdump(\"sf-dump\")</script>\n",
            ],
            [
                ['foo' => 'bar'],
                [],
                "<pre class=sf-dump id=sf-dump data-indent-pad=\"  \"><span class=sf-dump-note>array:1</span> [<samp>\n"
                ."  \"<span class=sf-dump-key>foo</span>\" => \"<span class=sf-dump-str title=\"3 characters\">bar</span>\"\n"
                ."</samp>]\n"
                ."</pre><script>Sfdump(\"sf-dump\")</script>\n",
            ],
        ];
    }

    public function testCustomDumper()
    {
        $output = '';
        $lineDumper = function ($line) use (&$output) {
            $output .= $line;
        };

        $dumper = new HtmlDumper($lineDumper);

        $dumper->setDumpHeader('');
        $dumper->setDumpBoundaries(
            '<pre class=sf-dump-test id=%s data-indent-pad="%s">',
            '</pre><script>Sfdump("%s")</script>'
        );
        $extension = new DumpExtension(new VarCloner(), $dumper);
        $twig = new Environment($this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock(), [
            'debug' => true,
            'cache' => false,
            'optimizations' => 0,
        ]);

        $dump = $extension->dump($twig, [], 'foo');
        $dump = preg_replace('/sf-dump-\d+/', 'sf-dump', $dump);

        $this->assertEquals(
            '<pre class=sf-dump-test id=sf-dump data-indent-pad="  ">"'.
            "<span class=sf-dump-str title=\"3 characters\">foo</span>\"\n".
            "</pre><script>Sfdump(\"sf-dump\")</script>\n",
            $dump,
            'Custom dumper should be used to dump data.'
        );

        $this->assertEmpty($output, 'Dumper output should be ignored.');
    }
}
