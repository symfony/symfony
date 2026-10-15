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

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\VarDumper;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

class SourceContextProviderTest extends TestCase
{
    public function testTwigFileExcerptWithCrlfLineEndings()
    {
        $source = "<div>\n{{ dump(1) }}\n</div>\n";
        $expected = '<ol start="1"><li><code>&lt;div&gt;</code></li>'."\n"
            .'<li class="selected"><code>{{ dump(1) }}</code></li>'."\n"
            .'<li><code>&lt;/div&gt;</code></li>'."\n"
            .'<li><code></code></li></ol>';

        $this->assertSame($expected, $this->render(new SourceContextProvider(), $source)[0]['file_excerpt']);
        $this->assertSame($expected, $this->render(new SourceContextProvider(), str_replace("\n", "\r\n", $source))[0]['file_excerpt']);
        $this->assertSame($expected, $this->render(new SourceContextProvider(), str_replace("\n", "\r", $source))[0]['file_excerpt']);
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
