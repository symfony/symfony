<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Profiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\Profiler\CodeExtension;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class CodeExtensionTest extends TestCase
{
    public function testFormatFile()
    {
        $expected = \sprintf('<a href="proto://foobar%s#&amp;line=25" title="Click to open this file" class="file_link">%s at line 25</a>', substr(__FILE__, 5), __FILE__);
        $this->assertEquals($expected, $this->getExtension()->formatFile(__FILE__, 25));
    }

    public function testFileRelative()
    {
        $this->assertEquals('file.txt', $this->getExtension()->getFileRelative(\DIRECTORY_SEPARATOR.'project'.\DIRECTORY_SEPARATOR.'file.txt'));
    }

    public function testClassAbbreviationIntegration()
    {
        $data = [
            'fqcn' => 'F\Q\N\Foo',
            'xss' => '<script>',
        ];

        $template = <<<'TWIG'
{{ 'Bare'|abbr_class }}
{{ fqcn|abbr_class }}
{{ xss|abbr_class }}
TWIG;

        $expected = <<<'HTML'
<abbr title="Bare">Bare</abbr>
<abbr title="F\Q\N\Foo">Foo</abbr>
<abbr title="&lt;script&gt;">&lt;script&gt;</abbr>
HTML;

        $this->assertEquals($expected, $this->render($template, $data));
    }

    public function testMethodAbbreviationIntegration()
    {
        $data = [
            'fqcn' => 'F\Q\N\Foo::Method',
            'xss' => '<script>',
        ];

        $template = <<<'TWIG'
{{ 'Bare::Method'|abbr_method }}
{{ fqcn|abbr_method }}
{{ 'Closure'|abbr_method }}
{{ 'Method'|abbr_method }}
{{ xss|abbr_method }}
TWIG;

        $expected = <<<'HTML'
<abbr title="Bare">Bare</abbr>::Method()
<abbr title="F\Q\N\Foo">Foo</abbr>::Method()
<abbr title="Closure">Closure</abbr>
<abbr title="Method">Method</abbr>()
<abbr title="&lt;script&gt;">&lt;script&gt;</abbr>()
HTML;

        $this->assertEquals($expected, $this->render($template, $data));
    }

    public function testFormatArgsIntegration()
    {
        $data = [
            'args' => [
                ['object', 'Foo'],
                ['array', [['string', 'foo'], ['null']]],
                ['resource'],
                ['string', 'bar'],
                ['int', 123],
                ['bool', true],
            ],
            'xss' => [
                ['object', '<Foo>'],
                ['array', [['string', '<foo>']]],
                ['string', '<bar>'],
                ['int', 123],
                ['bool', true],
                ['<xss>', '<script>'],
            ],
        ];

        $template = <<<'TWIG'
{{ args|format_args }}
{{ xss|format_args }}
{{ args|format_args_as_text }}
{{ xss|format_args_as_text }}
TWIG;

        $expected = <<<'HTML'
<em>object</em>(<abbr title="Foo">Foo</abbr>), <em>array</em>('foo', <em>null</em>), <em>resource</em>, 'bar', 123, true
<em>object</em>(<abbr title="&lt;Foo&gt;">&lt;Foo&gt;</abbr>), <em>array</em>('&lt;foo&gt;'), '&lt;bar&gt;', 123, true, '&lt;script&gt;'
object(Foo), array(&#039;foo&#039;, null), resource, &#039;bar&#039;, 123, true
object(&amp;lt;Foo&amp;gt;), array(&#039;&amp;lt;foo&amp;gt;&#039;), &#039;&amp;lt;bar&amp;gt;&#039;, 123, true, &#039;&amp;lt;script&amp;gt;&#039;
HTML;

        $this->assertEquals($expected, $this->render($template, $data));
    }

    public function testFormatFileIntegration()
    {
        $template = <<<'TWIG'
{{ 'foo/bar/baz.php'|format_file(21) }}
TWIG;

        $expected = <<<'HTML'
<a href="proto://foo/bar/baz.php#&amp;line=21" title="Click to open this file" class="file_link">foo/bar/baz.php at line 21</a>
HTML;

        $this->assertEquals($expected, $this->render($template));
    }

    public function testFormatFileFromTextIntegration()
    {
        $template = <<<'TWIG'
{{ 'in "foo/bar/baz.php" at line 21'|format_file_from_text }}
{{ 'in &quot;foo/bar/baz.php&quot; on line 21'|format_file_from_text }}
{{ 'in "<script>" on line 21'|format_file_from_text }}
TWIG;

        $expected = <<<'HTML'
in <a href="proto://foo/bar/baz.php#&amp;line=21" title="Click to open this file" class="file_link">foo/bar/baz.php at line 21</a>
in <a href="proto://foo/bar/baz.php#&amp;line=21" title="Click to open this file" class="file_link">foo/bar/baz.php at line 21</a>
in <a href="proto://&lt;script&gt;#&amp;line=21" title="Click to open this file" class="file_link">&lt;script&gt; at line 21</a>
HTML;

        $this->assertEquals($expected, $this->render($template));
    }

    protected function getExtension(): CodeExtension
    {
        return new CodeExtension(new FileLinkFormatter('proto://%f#&line=%l&'.substr(__FILE__, 0, 5).'>foobar'), \DIRECTORY_SEPARATOR.'project', 'UTF-8');
    }

    private function render(string $template, array $context = [])
    {
        $twig = new Environment(
            new ArrayLoader(['index' => $template]),
            ['debug' => true]
        );
        $twig->addExtension($this->getExtension());

        return $twig->render('index', $context);
    }
}
