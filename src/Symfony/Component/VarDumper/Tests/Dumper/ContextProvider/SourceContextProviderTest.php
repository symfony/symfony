<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Dumper\ContextProvider;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

class SourceContextProviderTest extends TestCase
{
    public function testTwigFileExcerpt()
    {
        $source = "<div>\n{% for item in items %}\n{{ dump(item) }}\n{% endfor %}\n</div>\n";
        $contexts = $this->render(new SourceContextProvider(), $source);

        $this->assertCount(3, $contexts);
        $this->assertSame($contexts[0], $contexts[2]);
        $this->assertSame(3, $contexts[0]['line']);
        $this->assertSame(
            '<ol start="1"><li><code>&lt;div&gt;</code></li>'."\n"
            .'<li><code>{% for item in items %}</code></li>'."\n"
            .'<li class="selected"><code>{{ dump(item) }}</code></li>'."\n"
            .'<li><code>{% endfor %}</code></li>'."\n"
            .'<li><code>&lt;/div&gt;</code></li>'."\n"
            .'<li><code></code></li></ol>',
            $contexts[0]['file_excerpt']
        );
    }

    #[DataProvider('provideLines')]
    public function testFileExcerptLineMatchesHtmlDumper(string $line)
    {
        $contexts = $this->render(new SourceContextProvider(), $line."\n{{ dump(item) }}");

        $this->assertStringContainsString('<li><code>'.$this->dumpAsHtml($line).'</code></li>', $contexts[0]['file_excerpt']);
    }

    public static function provideLines(): iterable
    {
        yield 'printable ASCII' => [implode('', array_map('chr', range(0x20, 0x7E)))];
        yield 'HTML' => ['<a href="#" title=\'x\'>&amp;</a>'];
        yield 'tab' => ["\t<p>{{ foo }}</p>"];
        yield 'carriage return' => ["<p>{{ foo }}</p>\r"];
        yield 'UTF-8' => ['<p>Café ☕</p>'];
        yield 'invisible Unicode' => ["<p>a\u{200B}b</p>"];
    }

    public function testDefaultCharsetCanChange()
    {
        $charset = \ini_get('default_charset');
        $provider = new SourceContextProvider();
        $source = "\xa4\n{{ dump(item) }}";

        try {
            ini_set('default_charset', 'ISO-8859-1');
            $first = $this->render($provider, $source);
            ini_set('default_charset', 'ISO-8859-15');
            $second = $this->render($provider, $source);

            $this->assertNotSame($first[0]['file_excerpt'], $second[0]['file_excerpt']);
        } finally {
            ini_set('default_charset', $charset);
        }
    }

    private function dumpAsHtml(string $s): string
    {
        $html = '';
        $dumper = new HtmlDumper(static function ($line) use (&$html) { $html .= $line; });
        $dumper->setDumpHeader('');
        $dumper->setDumpBoundaries('', '');
        $dumper->dump((new VarCloner())->cloneVar($s));

        return substr(strip_tags($html), 1, -1);
    }

    private function render(SourceContextProvider $provider, string $source): array
    {
        $twig = new Environment(new ArrayLoader(['example' => $source]), ['debug' => true]);
        $twig->addFunction(new TwigFunction('dump', [VarDumper::class, 'dump']));
        $contexts = [];
        $previous = VarDumper::setHandler(static function () use ($provider, &$contexts) {
            $contexts[] = $provider->getContext();
        });

        try {
            $twig->render('example', ['items' => [1, 2, 3]]);
        } finally {
            VarDumper::setHandler($previous);
        }

        return $contexts;
    }
}
